<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\Api\CredentialMasker;
use DmmApiClient\Api\Exception\ApiErrorException;
use DmmApiClient\Api\Exception\TransportException;
use DmmApiClient\Api\Response\Error\ErrorResponse;
use Http\Discovery\Exception\NotFoundException;

/**
 * 対象を 1 本ずつ叩き、レスポンスを保存して検証する。
 */
final class Runner
{
    /** 同じリクエストを試す回数。 */
    private const int MAX_ATTEMPTS = 3;

    /** 再試行までの待ち時間（秒）。 */
    private const array BACKOFF = [2, 5];

    /**
     * エラーを引くつもりのリクエストが成功してしまった場合に、検証エラーとして記録する内容。
     *
     * DTO のフィールドではないので、`*` で囲んだ擬似的なパスにする（{@see Validator} と同じ書き方）。
     */
    private const string UNEXPECTED_OK_PATH = '*outcome*';

    private const string UNEXPECTED_OK_MESSAGE
        = 'Expected the API to reject this request, but it returned a successful response. '
        . 'The error DTOs got no coverage from it.';

    /** 直前のリクエストを送った時刻。レート制御に使う。 */
    private float $lastRequestAt = 0.0;

    /** 実際に送ったリクエストの本数。 */
    private int $sent = 0;

    /** @var list<Record> この実行で処理した記録。 */
    private array $records = [];

    /**
     * @param array<string, Record> $previous `--resume` のときに引き継ぐ、前回の記録（ファイルの相対パスをキーにする）
     */
    public function __construct(
        private readonly Clients $clients,
        private readonly CredentialMasker $masker,
        private readonly Validator $validator,
        private readonly RunDirectory $run,
        private readonly Options $options,
        private readonly array $previous = [],
    ) {}

    public function sent(): int
    {
        return $this->sent;
    }

    /**
     * `--limit` に達したか。
     *
     * 送信数と上限の両方を持っているのはここだけなので、判断もここに置く。
     * 打ち切りは各フェーズのループが対象と対象の境目で見る。途中の対象は
     * ページを取り切ってから止まる（{@see Probe} の使い方の説明を参照）。
     */
    public function limitReached(): bool
    {
        return $this->options->limit !== null && $this->sent >= $this->options->limit;
    }

    /**
     * この実行で処理した記録。
     *
     * レポートはこれを集計する。run ディレクトリには過去の実行の分も溜まっているが、
     * 1 回の実行が報告するのは、その回に処理した分だけにする。
     *
     * @return list<Record>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * 1 本のリクエストを処理し、結果を控える。
     *
     * @throws NotFoundException PSR-18 / PSR-17 の実装が見つからない場合
     * @throws ProbeException    レスポンスを保存できなかった場合
     */
    public function execute(Target $target, int $offset, ?string $page): Record
    {
        $record = $this->fetch($target, $offset, $page);
        $this->records[] = $record;

        return $record;
    }

    /**
     * 1 本のリクエストを処理する。
     *
     * `--resume` で既に保存済みのファイルがある場合は取得せず、保存済みのボディを検証する。
     *
     * @throws NotFoundException PSR-18 / PSR-17 の実装が見つからない場合
     * @throws ProbeException    レスポンスを保存できなかった場合
     */
    private function fetch(Target $target, int $offset, ?string $page): Record
    {
        $request = $target->request($offset);
        $relative = $target->group . '/' . $target->fileName($offset);
        $client = $this->clients->forTarget($target);
        $uri = $this->masker->mask($client->buildUri($request));

        if ($this->options->resume && $this->run->has($relative)) {
            return $this->cached($target, $offset, $page, $relative, $uri);
        }

        // 測るのは送受信にかかった時間だけ。レート制御や再試行の待ち時間は含めない。
        // 待っているのはこちらの都合で、API がどれだけ待たせたかとは関係が無い。
        // 再試行した場合は、各回にかかった時間の合計になる。
        $spent = 0;
        $attempt = 1;

        while (true) {
            $this->throttle();
            $this->sent++;
            $attemptAt = microtime(true);

            try {
                $body = $client->fetchRaw($request);
                $spent += self::elapsed($attemptAt);

                // エラーを引くつもりの対象が通ってしまった場合。API の挙動が変わったか、
                // 壊したはずの認証情報が受け入れられたかで、いずれにせよ前提が崩れている。
                $unexpected = $target->expectsError;

                return $this->store(
                    $target,
                    $offset,
                    $page,
                    $relative,
                    $uri,
                    $body,
                    $unexpected ? Record::OUTCOME_UNEXPECTED_OK : Record::OUTCOME_OK,
                    200,
                    $unexpected ? self::UNEXPECTED_OK_MESSAGE : null,
                    $spent,
                );
            } catch (ApiErrorException $exception) {
                $spent += self::elapsed($attemptAt);

                if ($attempt < self::MAX_ATTEMPTS && self::isRetryable($exception->httpStatusCode)) {
                    $this->wait($attempt++);

                    continue;
                }

                // エラーボディも保存する。エラー用 DTO が実際の形と合っているかも確かめたいので、
                // 検証は ErrorResponse に対して行う。
                return $this->store(
                    $target,
                    $offset,
                    $page,
                    $relative,
                    $uri,
                    $exception->responseBody,
                    Record::OUTCOME_API_ERROR,
                    $exception->httpStatusCode,
                    $exception->getMessage(),
                    $spent,
                    ErrorResponse::class,
                );
            } catch (TransportException $exception) {
                $spent += self::elapsed($attemptAt);

                if ($attempt < self::MAX_ATTEMPTS) {
                    $this->wait($attempt++);

                    continue;
                }

                return $this->store(
                    $target,
                    $offset,
                    $page,
                    $relative,
                    $uri,
                    null,
                    Record::OUTCOME_TRANSPORT_ERROR,
                    null,
                    $exception->getMessage(),
                    $spent,
                );
            }
        }
    }

    /**
     * 保存済みのファイルを検証し直すだけで済ませる。
     *
     * 前回の記録が残っていれば、どの DTO で検証したか・API がエラーを返したかを引き継ぐ。
     */
    private function cached(Target $target, int $offset, ?string $page, string $relative, string $uri): Record
    {
        $previous = $this->previous[$relative] ?? null;
        $body = $this->run->read($relative) ?? '';
        $responseClass = $previous === null ? $target->responseClass : $previous->responseClass;
        $errors = $this->validator->validate($responseClass, $body);
        $unknownKeys = $this->validator->unknownKeys($responseClass, $body);
        $decoded = Json::decode($body);

        return new Record(
            group: $target->group,
            endpoint: $target->endpoint,
            responseClass: $responseClass,
            file: $relative,
            context: $target->context,
            sort: $target->sort,
            hits: $target->hits,
            offset: $target->isSingle() ? null : $offset,
            page: $page,
            uri: $uri,
            outcome: $previous === null ? Record::OUTCOME_UNKNOWN : $previous->outcome,
            httpStatus: $previous?->httpStatus,
            totalCount: $decoded === null ? null : Json::resultInt($decoded, 'total_count'),
            resultCount: $decoded === null ? null : Json::resultInt($decoded, 'result_count'),
            validation: $errors === [] ? Record::VALIDATION_OK : Record::VALIDATION_FAILED,
            errors: $errors,
            message: $previous?->message,
            durationMs: 0,
            unknownKeys: $unknownKeys,
            cached: true,
        );
    }

    /**
     * 保存と検証をまとめる。
     *
     * 保存は伏せ字にしたボディを、検証は受け取ったままのボディを対象にする。
     * 伏せ字は値を置き換えるだけで構造を変えないため、保存したファイルを
     * あとから検証し直しても（`--revalidate`）結果は変わらない。
     *
     * @param class-string|null $responseClass 対象の既定の DTO ではなく、別の DTO で検証する場合に指定する
     *
     * @throws ProbeException レスポンスを保存できなかった場合
     */
    private function store(
        Target $target,
        int $offset,
        ?string $page,
        string $relative,
        string $uri,
        ?string $body,
        string $outcome,
        ?int $httpStatus,
        ?string $message,
        int $durationMs,
        ?string $responseClass = null,
    ): Record {
        $responseClass ??= $target->responseClass;
        $decoded = $body === null ? null : Json::decode($body);

        if ($body !== null) {
            $this->run->save($relative, $this->masker->mask($body));
        }

        // 成功してしまった対象のボディを、エラー用の DTO で検証しても意味のある結果にならない。
        // 検証の代わりに、前提が崩れたことをそのまま検証エラーとして記録する。
        $errors = match (true) {
            $body === null => [],
            $outcome === Record::OUTCOME_UNEXPECTED_OK => [
                ['path' => self::UNEXPECTED_OK_PATH, 'message' => self::UNEXPECTED_OK_MESSAGE],
            ],
            default => $this->validator->validate($responseClass, $body),
        };
        $unknownKeys = $body === null || $outcome === Record::OUTCOME_UNEXPECTED_OK
            ? []
            : $this->validator->unknownKeys($responseClass, $body);
        $validation = match (true) {
            $body === null => Record::VALIDATION_SKIPPED,
            $errors === [] => Record::VALIDATION_OK,
            default => Record::VALIDATION_FAILED,
        };

        return new Record(
            group: $target->group,
            endpoint: $target->endpoint,
            responseClass: $responseClass,
            file: $body === null ? null : $relative,
            context: $target->context,
            sort: $target->sort,
            hits: $target->hits,
            offset: $target->isSingle() ? null : $offset,
            page: $page,
            uri: $uri,
            outcome: $outcome,
            httpStatus: $httpStatus,
            totalCount: $decoded === null ? null : Json::resultInt($decoded, 'total_count'),
            resultCount: $decoded === null ? null : Json::resultInt($decoded, 'result_count'),
            validation: $validation,
            errors: $errors,
            message: $message === null ? null : $this->masker->mask($message),
            durationMs: $durationMs,
            unknownKeys: $unknownKeys,
        );
    }

    /**
     * `--rate` で指定された間隔があくまで待つ。
     */
    private function throttle(): void
    {
        if ($this->options->rate <= 0.0) {
            return;
        }

        $interval = 1.0 / $this->options->rate;
        $wait = $this->lastRequestAt + $interval - microtime(true);

        if ($wait > 0) {
            usleep((int) round($wait * 1_000_000));
        }

        $this->lastRequestAt = microtime(true);
    }

    private function wait(int $attempt): void
    {
        $backoff = self::BACKOFF;

        sleep($backoff[$attempt - 1] ?? end($backoff));
    }

    private static function isRetryable(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private static function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
