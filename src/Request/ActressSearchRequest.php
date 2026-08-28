<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

use DateTimeImmutable;
use DmmApiClient\Exception\InvalidArgumentException;

/**
 * 女優検索 API (`/ActressSearch`) のリクエスト。
 */
final readonly class ActressSearchRequest implements Request
{
    /** API のエンドポイントパス。 */
    public const string ENDPOINT = '/ActressSearch';

    /** 取得件数の最小値。 */
    public const int HITS_MIN = 1;

    /** 取得件数の最大値。 */
    public const int HITS_MAX = 100;

    /** 検索開始位置の最小値。 */
    public const int OFFSET_MIN = 1;

    /**
     * @param string|null            $initial     女優名かなの前方一致（2 文字以上も指定できる。例: あ、あさ）
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
        public ?string $initial = null,
        public ?string $actressId = null,
        public ?string $keyword = null,
        public ?int $gteBust = null,
        public ?int $lteBust = null,
        public ?int $gteWaist = null,
        public ?int $lteWaist = null,
        public ?int $gteHip = null,
        public ?int $lteHip = null,
        public ?int $gteHeight = null,
        public ?int $lteHeight = null,
        public ?DateTimeImmutable $gteBirthday = null,
        public ?DateTimeImmutable $lteBirthday = null,
        public ?ActressSearchSort $sort = null,
        public ?int $hits = null,
        public ?int $offset = null,
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
        return self::ENDPOINT;
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
