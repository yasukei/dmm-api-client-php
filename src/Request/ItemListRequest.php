<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

use DateTimeImmutable;
use DmmApiClient\Exception\InvalidArgumentException;
use DmmApiClient\SiteCode;

/**
 * 商品情報 API (`/ItemList`) のリクエスト。
 */
final class ItemListRequest implements Request
{
    /** 取得件数の最小値。 */
    public const int HITS_MIN = 1;

    /** 取得件数の最大値。 */
    public const int HITS_MAX = 100;

    /** 検索開始位置の最小値。 */
    public const int OFFSET_MIN = 1;

    /** 検索開始位置の最大値。 */
    public const int OFFSET_MAX = 50000;

    /**
     * @param SiteCode             $site      検索対象サイト
     * @param string|null          $service   サービスコードによる絞り込み（例: digital、mono）
     * @param string|null          $floor     フロアコードによる絞り込み（例: videoa、books）
     * @param string|null          $keyword   検索キーワード（URL エンコードは不要）
     * @param string|null          $cid       商品 ID を指定して 1 件だけ取得する場合の ID
     * @param list<ArticleFilter>  $articles  カテゴリによる絞り込み条件。複数指定できる
     * @param DateTimeImmutable|null $gteDate この日時以降に発売・配信された商品に絞り込む
     * @param DateTimeImmutable|null $lteDate この日時以前に発売・配信された商品に絞り込む
     * @param MonoStock|null       $monoStock 通販商品の在庫状況による絞り込み
     * @param ItemListSort|null    $sort      並び順（未指定時は API 既定の rank）
     * @param int|null             $hits      取得件数（1〜100。未指定時は API 既定の 20）
     * @param int|null             $offset    検索開始位置（1〜50000、1 始まり。未指定時は API 既定の 1）
     *
     * @throws InvalidArgumentException $hits / $offset が範囲外の場合
     */
    public function __construct(
        public readonly SiteCode $site,
        public readonly ?string $service = null,
        public readonly ?string $floor = null,
        public readonly ?string $keyword = null,
        public readonly ?string $cid = null,
        public readonly array $articles = [],
        public readonly ?DateTimeImmutable $gteDate = null,
        public readonly ?DateTimeImmutable $lteDate = null,
        public readonly ?MonoStock $monoStock = null,
        public readonly ?ItemListSort $sort = null,
        public readonly ?int $hits = null,
        public readonly ?int $offset = null,
    ) {
        if ($hits !== null && ($hits < self::HITS_MIN || $hits > self::HITS_MAX)) {
            throw new InvalidArgumentException(
                sprintf('hits must be between %d and %d, %d given.', self::HITS_MIN, self::HITS_MAX, $hits),
            );
        }

        if ($offset !== null && ($offset < self::OFFSET_MIN || $offset > self::OFFSET_MAX)) {
            throw new InvalidArgumentException(
                sprintf('offset must be between %d and %d, %d given.', self::OFFSET_MIN, self::OFFSET_MAX, $offset),
            );
        }
    }

    public function endpoint(): string
    {
        return '/ItemList';
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toQueryParameters(): array
    {
        $parameters = array_filter(
            [
                'site' => $this->site->value,
                'service' => $this->service,
                'floor' => $this->floor,
                'keyword' => $this->keyword,
                'cid' => $this->cid,
                'gte_date' => $this->gteDate?->format('Y-m-d\TH:i:s'),
                'lte_date' => $this->lteDate?->format('Y-m-d\TH:i:s'),
                'mono_stock' => $this->monoStock?->value,
                'sort' => $this->sort?->value,
                'hits' => $this->hits === null ? null : (string) $this->hits,
                'offset' => $this->offset === null ? null : (string) $this->offset,
            ],
            static fn (?string $value): bool => $value !== null,
        );

        if ($this->articles === []) {
            return $parameters;
        }

        return $parameters + [
            'article' => array_map(
                static fn (ArticleFilter $article): string => $article->type->value,
                $this->articles,
            ),
            'article_id' => array_map(
                static fn (ArticleFilter $article): string => $article->id,
                $this->articles,
            ),
        ];
    }
}
