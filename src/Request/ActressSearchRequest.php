<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

use DateTimeImmutable;
use DmmApiClient\Exception\InvalidArgumentException;

/**
 * 女優検索 API (`/ActressSearch`) のリクエスト。
 */
final class ActressSearchRequest implements Request
{
    /** 取得件数の最小値。 */
    public const int HITS_MIN = 1;

    /** 取得件数の最大値。 */
    public const int HITS_MAX = 100;

    /** 検索開始位置の最小値。 */
    public const int OFFSET_MIN = 1;

    /**
     * @param string|null            $initial     女優名かなの頭文字（五十音 1 文字。例: あ）
     * @param string|null            $actressId   女優 ID を指定して 1 件だけ取得する場合の ID
     * @param string|null            $keyword     女優名の検索キーワード
     * @param int|null               $gteBust     バストがこの値以上の女優に絞り込む（cm）
     * @param int|null               $lteBust     バストがこの値以下の女優に絞り込む（cm）
     * @param int|null               $gteWaist    ウエストがこの値以上の女優に絞り込む（cm）
     * @param int|null               $lteWaist    ウエストがこの値以下の女優に絞り込む（cm）
     * @param int|null               $gteHip      ヒップがこの値以上の女優に絞り込む（cm）
     * @param int|null               $lteHip      ヒップがこの値以下の女優に絞り込む（cm）
     * @param int|null               $gteHeight   身長がこの値以上の女優に絞り込む（cm）
     * @param int|null               $lteHeight   身長がこの値以下の女優に絞り込む（cm）
     * @param DateTimeImmutable|null $gteBirthday 生年月日がこの日以降の女優に絞り込む
     * @param DateTimeImmutable|null $lteBirthday 生年月日がこの日以前の女優に絞り込む
     * @param ActressSearchSort|null $sort        並び順（未指定時は API 既定の id）
     * @param int|null               $hits        取得件数（1〜100。未指定時は API 既定の 20）
     * @param int|null               $offset      検索開始位置（1 以上、1 始まり。未指定時は API 既定の 1）
     *
     * @throws InvalidArgumentException $hits / $offset が範囲外の場合
     */
    public function __construct(
        public readonly ?string $initial = null,
        public readonly ?string $actressId = null,
        public readonly ?string $keyword = null,
        public readonly ?int $gteBust = null,
        public readonly ?int $lteBust = null,
        public readonly ?int $gteWaist = null,
        public readonly ?int $lteWaist = null,
        public readonly ?int $gteHip = null,
        public readonly ?int $lteHip = null,
        public readonly ?int $gteHeight = null,
        public readonly ?int $lteHeight = null,
        public readonly ?DateTimeImmutable $gteBirthday = null,
        public readonly ?DateTimeImmutable $lteBirthday = null,
        public readonly ?ActressSearchSort $sort = null,
        public readonly ?int $hits = null,
        public readonly ?int $offset = null,
    ) {
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
        return '/ActressSearch';
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toQueryParameters(): array
    {
        return array_filter(
            [
                'initial' => $this->initial,
                'actress_id' => $this->actressId,
                'keyword' => $this->keyword,
                'gte_bust' => $this->gteBust === null ? null : (string) $this->gteBust,
                'lte_bust' => $this->lteBust === null ? null : (string) $this->lteBust,
                'gte_waist' => $this->gteWaist === null ? null : (string) $this->gteWaist,
                'lte_waist' => $this->lteWaist === null ? null : (string) $this->lteWaist,
                'gte_hip' => $this->gteHip === null ? null : (string) $this->gteHip,
                'lte_hip' => $this->lteHip === null ? null : (string) $this->lteHip,
                'gte_height' => $this->gteHeight === null ? null : (string) $this->gteHeight,
                'lte_height' => $this->lteHeight === null ? null : (string) $this->lteHeight,
                'gte_birthday' => $this->gteBirthday?->format('Y-m-d'),
                'lte_birthday' => $this->lteBirthday?->format('Y-m-d'),
                'sort' => $this->sort?->value,
                'hits' => $this->hits === null ? null : (string) $this->hits,
                'offset' => $this->offset === null ? null : (string) $this->offset,
            ],
            static fn (?string $value): bool => $value !== null,
        );
    }
}
