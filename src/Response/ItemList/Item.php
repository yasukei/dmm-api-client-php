<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DateTimeImmutable;

/**
 * 商品情報 1 件。
 */
final class Item
{
    /**
     * @param string                 $serviceCode    サービスコード（例: digital）
     * @param string                 $serviceName    サービス名（例: 動画）
     * @param string                 $floorCode      フロアコード（例: videoa）
     * @param string                 $floorName      フロア名（例: ビデオ）
     * @param string                 $categoryName   カテゴリ名（例: ビデオ (動画)）
     * @param string                 $contentId      商品 ID（例: mizd00320）
     * @param string                 $title          商品タイトル
     * @param string                 $url            商品ページの URL
     * @param string                 $affiliateUrl   商品ページのアフィリエイト URL
     * @param string|null            $productId      品番（例: 15dss00145dl）
     * @param string|null            $volume         収録時間（分）またはページ数（例: "120"）
     * @param int|null               $number         巻数
     * @param Review|null            $review         レビュー集計
     * @param ItemImageUrl|null      $imageUrl       商品画像の URL
     * @param Tachiyomi|null         $tachiyomi      立ち読みページへのリンク
     * @param SampleImageUrl|null    $sampleImageUrl サンプル画像
     * @param SampleMovieUrl|null    $sampleMovieUrl サンプル動画
     * @param ItemPrices|null        $prices         価格情報
     * @param DateTimeImmutable|null $date           発売日・配信開始日
     * @param ItemInfo|null          $iteminfo       ジャンル・女優などの分類情報
     */
    public function __construct(
        #[MapFromKey('service_code')]
        public readonly string $serviceCode,
        #[MapFromKey('service_name')]
        public readonly string $serviceName,
        #[MapFromKey('floor_code')]
        public readonly string $floorCode,
        #[MapFromKey('floor_name')]
        public readonly string $floorName,
        #[MapFromKey('category_name')]
        public readonly string $categoryName,
        #[MapFromKey('content_id')]
        public readonly string $contentId,
        public readonly string $title,
        #[MapFromKey('URL')]
        public readonly string $url,
        #[MapFromKey('affiliateURL')]
        public readonly string $affiliateUrl,
        #[MapFromKey('product_id')]
        public readonly ?string $productId = null,
        public readonly ?string $volume = null,
        public readonly ?int $number = null,
        public readonly ?Review $review = null,
        #[MapFromKey('imageURL')]
        public readonly ?ItemImageUrl $imageUrl = null,
        public readonly ?Tachiyomi $tachiyomi = null,
        #[MapFromKey('sampleImageURL')]
        public readonly ?SampleImageUrl $sampleImageUrl = null,
        #[MapFromKey('sampleMovieURL')]
        public readonly ?SampleMovieUrl $sampleMovieUrl = null,
        public readonly ?ItemPrices $prices = null,
        public readonly ?DateTimeImmutable $date = null,
        public readonly ?ItemInfo $iteminfo = null,
    ) {
    }
}
