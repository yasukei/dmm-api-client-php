<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\CredentialMasker;
use DmmApiClient\DmmApiClient;
use DmmApiClient\Exception\ApiErrorException;
use DmmApiClient\Exception\TransportException;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\Error\ErrorResponse;

/**
 * 対象を 1 本ずつ叩き、レスポンスを保存して検証する。
 */
final class Runner
{
    /** 同じリクエストを試す回数。 */
    private const int MAX_ATTEMPTS = 3;

    /** 再試行までの待ち時間（秒）。 */
    private const array BACKOFF = [2, 5];

    /** 直前のリクエストを送った時刻。レート制御に使う。 */
    private float $lastRequestAt = 0.0;

    /** 実際に送ったリクエストの本数。 */
    private int $sent = 0;

    /**
     * @param array<string, Record> $previous `--resume` のときに引き継ぐ、前回の記録（ファイルの相対パスをキーにする）
     */
    public function __construct(
        private readonly DmmApiClient $client,
        private readonly CredentialMasker $masker,
        private readonly Validator $validator,
        private readonly RunDirectory $run,
        private readonly Options $options,
        private readonly array $previous = [],
    ) {
    }

    public function sent(): int
    {
        return $this->sent;
    }

    /**
     * 1 本のリクエストを処理する。
     *
     * `--resume` で既に保存済みのファイルがある場合は取得せず、保存済みのボディを検証する。
     */
    public function execute(Target $target, int $offset, ?string $page): Record
    {
        $request = $target->request($offset);
        $relative = $target->group . '/' . $target->fileName($offset);
        $uri = $this->masker->mask($this->client->buildUri($request));

        if ($this->options->resume && $this->run->has($relative)) {
            return $this->cached($target, $offset, $page, $relative, $uri);
        }

        $startedAt = microtime(true);
        $attempt = 1;

        while (true) {
            $this->throttle();
            $this->sent++;

            try {
                $body = $this->client->fetchRaw($request);

                return $this->store(
                    $target,
                    $offset,
                    $page,
                    $relative,
                    $uri,
                    $body,
                    Record::OUTCOME_OK,
                    200,
                    null,
                    self::elapsed($startedAt),
                );
            } catch (ApiErrorException $exception) {
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
                    self::elapsed($startedAt),
                    ErrorResponse::class,
                );
            } catch (TransportException $exception) {
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
                    self::elapsed($startedAt),
                );
            }
        }
    }

    /**
     * 送信せずに URI だけを見たい場合（`--dry-run`）に使う。
     */
    public function uri(Request $request): string
    {
        return $this->masker->mask($this->client->buildUri($request));
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

        $errors = $body === null ? [] : $this->validator->validate($responseClass, $body);
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

        sleep($backoff[$attempt - 1] ?? (int) end($backoff));
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
