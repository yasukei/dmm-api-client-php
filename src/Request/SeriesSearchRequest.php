<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

use DmmApiClient\Exception\InvalidArgumentException;

/**
 * シリーズ検索 API (`/SeriesSearch`) のリクエスト。
 */
final readonly class SeriesSearchRequest implements Request
{
    /** API のエンドポイントパス。 */
    public const string ENDPOINT = '/SeriesSearch';

    /** 取得件数の最小値。 */
    public const int HITS_MIN = 1;

    /** 取得件数の最大値。 */
    public const int HITS_MAX = 500;

    /** 検索開始位置の最小値。 */
    public const int OFFSET_MIN = 1;

    /**
     * @param string      $floorId フロア ID。シリーズが属するフロアを指定する（例: "43"）
     * @param string|null $initial シリーズ名かなの頭文字（五十音 1 文字。例: あ）
     * @param int|null    $hits    取得件数（1〜500。未指定時は API 既定の 100）
     * @param int|null    $offset  検索開始位置（1 以上、1 始まり。未指定時は API 既定の 1）
     *
     * @throws InvalidArgumentException $floorId が空、または $hits / $offset が範囲外の場合
     */
    public function __construct(
        public string $floorId,
        public ?string $initial = null,
        public ?int $hits = null,
        public ?int $offset = null,
    ) {
        if ($floorId === '') {
            throw new InvalidArgumentException('floor_id must not be empty.');
        }

        if ($hits !== null && ($hits < self::HITS_MIN || $hits > self::HITS_MAX)) {
            throw new InvalidArgumentException(
                sprintf('hits must be between %d and %d, %d given.', self::HITS_MIN, self::HITS_MAX, $hits),
            );
        }

        if ($offset !== null && $offset < self::OFFSET_MIN) {
            throw new InvalidArgumentException(
                sprintf('offset must be %d or greater, %d given.', self::OFFSET_MIN, $offset),
            );
        }
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toQueryParameters(): array
    {
        return array_filter(
            [
                'floor_id' => $this->floorId,
                'initial' => $this->initial,
                'hits' => $this->hits === null ? null : (string) $this->hits,
                'offset' => $this->offset === null ? null : (string) $this->offset,
            ],
            static fn (?string $value): bool => $value !== null,
        );
    }
}
