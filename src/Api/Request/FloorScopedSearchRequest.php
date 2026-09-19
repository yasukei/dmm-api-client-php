<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

use DmmApiClient\Api\Exception\InvalidArgumentException;

/**
 * フロア ID を必須とする検索 API（ジャンル・メーカー・シリーズ・作者）の共通部分。
 *
 * 4 つとも受け付けるパラメータが同じで、値の範囲も揃っているため、
 * 個々のリクエストはエンドポイントだけを持つ。
 */
abstract readonly class FloorScopedSearchRequest implements Request
{
    use ValidatesHitsAndOffset;

    /** 取得件数の最小値。 */
    public const int HITS_MIN = 1;

    /** 取得件数の最大値。 */
    public const int HITS_MAX = 500;

    /** 検索開始位置の最小値。 */
    public const int OFFSET_MIN = 1;

    /**
     * @param string      $floorId フロア ID。検索対象が属するフロアを指定する（例: "43"）
     * @param string|null $initial 名前かなの前方一致（2 文字以上も指定できる。例: あ、あさ）
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

        self::assertHitsInRange($hits, static::HITS_MIN, static::HITS_MAX);
        self::assertOffsetInRange($offset, static::OFFSET_MIN);
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
            static fn(?string $value): bool => $value !== null,
        );
    }
}
