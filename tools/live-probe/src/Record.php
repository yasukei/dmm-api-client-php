<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

/**
 * リクエスト 1 本の結果。manifest.jsonl の 1 行に対応する。
 */
final readonly class Record
{
    /** 取得できた。 */
    public const string OUTCOME_OK = 'ok';

    /** 保存済みのファイルはあるが、前回どう終わったかが分からない（manifest が無い）。 */
    public const string OUTCOME_UNKNOWN = 'unknown';

    /** API がエラーを返した。 */
    public const string OUTCOME_API_ERROR = 'api-error';

    /** HTTP 通信に失敗した。 */
    public const string OUTCOME_TRANSPORT_ERROR = 'transport-error';

    public const string VALIDATION_OK = 'ok';

    public const string VALIDATION_FAILED = 'failed';

    /** ボディが無いなど、検証できなかった。 */
    public const string VALIDATION_SKIPPED = 'skipped';

    /**
     * @param string                                       $group         出力先のサブディレクトリ名
     * @param string                                       $endpoint      API のエンドポイントパス
     * @param class-string                                 $responseClass 検証に使った DTO
     * @param string|null                                  $file          run ディレクトリからの相対パス
     * @param array<string, string>                        $context       サイト・サービス・フロアの内訳
     * @param string                                       $uri           送信した URI（伏せ字済み）
     * @param list<array{path: string, message: string}>   $errors        検証エラー
     * @param list<array{path: string, message: string}>   $unknownKeys   DTO が知らないキー
     * @param bool                                         $cached        取得せず、保存済みのファイルを使ったか（`--resume`）
     */
    public function __construct(
        public string $group,
        public string $endpoint,
        public string $responseClass,
        public ?string $file,
        public array $context,
        public ?string $sort,
        public ?int $hits,
        public ?int $offset,
        public ?string $page,
        public string $uri,
        public string $outcome,
        public ?int $httpStatus,
        public ?int $totalCount,
        public ?int $resultCount,
        public string $validation,
        public array $errors,
        public ?string $message,
        public int $durationMs,
        public array $unknownKeys = [],
        public bool $cached = false,
    ) {
    }

    public function isFailure(): bool
    {
        return $this->validation === self::VALIDATION_FAILED
            || $this->outcome === self::OUTCOME_TRANSPORT_ERROR;
    }

    /**
     * 検証結果だけを差し替えた同じ記録を返す（`--revalidate` 用）。
     *
     * @param list<array{path: string, message: string}> $errors
     * @param list<array{path: string, message: string}> $unknownKeys
     */
    public function withValidation(string $validation, array $errors, array $unknownKeys): self
    {
        return new self(
            group: $this->group,
            endpoint: $this->endpoint,
            responseClass: $this->responseClass,
            file: $this->file,
            context: $this->context,
            sort: $this->sort,
            hits: $this->hits,
            offset: $this->offset,
            page: $this->page,
            uri: $this->uri,
            outcome: $this->outcome,
            httpStatus: $this->httpStatus,
            totalCount: $this->totalCount,
            resultCount: $this->resultCount,
            validation: $validation,
            errors: $errors,
            message: $this->message,
            durationMs: $this->durationMs,
            unknownKeys: $unknownKeys,
            cached: $this->cached,
        );
    }

    /**
     * ファイル名だけでは分からない条件も含めた、1 行の説明。
     */
    public function label(): string
    {
        $parts = [$this->endpoint];

        foreach (['site', 'service', 'floor'] as $key) {
            if (isset($this->context[$key])) {
                $parts[] = $this->context[$key];
            }
        }

        if ($this->sort !== null) {
            $parts[] = 'sort=' . $this->sort;
        }

        if ($this->offset !== null) {
            $parts[] = 'offset=' . $this->offset;
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'endpoint' => $this->endpoint,
            'responseClass' => $this->responseClass,
            'file' => $this->file,
            'context' => $this->context,
            'sort' => $this->sort,
            'hits' => $this->hits,
            'offset' => $this->offset,
            'page' => $this->page,
            'uri' => $this->uri,
            'outcome' => $this->outcome,
            'httpStatus' => $this->httpStatus,
            'totalCount' => $this->totalCount,
            'resultCount' => $this->resultCount,
            'validation' => $this->validation,
            'errors' => $this->errors,
            'message' => $this->message,
            'durationMs' => $this->durationMs,
            'unknownKeys' => $this->unknownKeys,
            'cached' => $this->cached,
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var class-string $responseClass */
        $responseClass = self::string($data, 'responseClass') ?? '';

        return new self(
            group: self::string($data, 'group') ?? '',
            endpoint: self::string($data, 'endpoint') ?? '',
            responseClass: $responseClass,
            file: self::string($data, 'file'),
            context: self::stringMap($data, 'context'),
            sort: self::string($data, 'sort'),
            hits: self::int($data, 'hits'),
            offset: self::int($data, 'offset'),
            page: self::string($data, 'page'),
            uri: self::string($data, 'uri') ?? '',
            outcome: self::string($data, 'outcome') ?? self::OUTCOME_OK,
            httpStatus: self::int($data, 'httpStatus'),
            totalCount: self::int($data, 'totalCount'),
            resultCount: self::int($data, 'resultCount'),
            validation: self::string($data, 'validation') ?? self::VALIDATION_SKIPPED,
            errors: self::errors($data, 'errors'),
            message: self::string($data, 'message'),
            durationMs: self::int($data, 'durationMs') ?? 0,
            unknownKeys: self::errors($data, 'unknownKeys'),
            cached: ($data['cached'] ?? false) === true,
        );
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, string>
     */
    private static function stringMap(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        $map = [];

        if (is_array($value)) {
            foreach ($value as $name => $item) {
                if (is_string($name) && is_string($item)) {
                    $map[$name] = $item;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array{path: string, message: string}>
     */
    private static function errors(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        $errors = [];

        if (! is_array($value)) {
            return [];
        }

        foreach ($value as $error) {
            if (! is_array($error)) {
                continue;
            }

            $path = $error['path'] ?? null;
            $message = $error['message'] ?? null;

            if (is_string($path) && is_string($message)) {
                $errors[] = ['path' => $path, 'message' => $message];
            }
        }

        return $errors;
    }
}
