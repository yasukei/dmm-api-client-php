<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DateTimeImmutable;

/**
 * 商品情報 1 件。
 */
final readonly class Item
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
     * @param string|null            $number         巻数（例: "1"）。電子書籍のフロアだけが返す
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
        public string $serviceCode,
        #[MapFromKey('service_name')]
        public string $serviceName,
        #[MapFromKey('floor_code')]
        public string $floorCode,
        #[MapFromKey('floor_name')]
        public string $floorName,
        #[MapFromKey('category_name')]
        public string $categoryName,
        #[MapFromKey('content_id')]
        public string $contentId,
        public string $title,
        #[MapFromKey('URL')]
        public string $url,
        #[MapFromKey('affiliateURL')]
        public string $affiliateUrl,
        #[MapFromKey('product_id')]
        public ?string $productId = null,
        public ?string $volume = null,
        public ?string $number = null,
        public ?Review $review = null,
        #[MapFromKey('imageURL')]
        public ?ItemImageUrl $imageUrl = null,
        public ?Tachiyomi $tachiyomi = null,
        #[MapFromKey('sampleImageURL')]
        public ?SampleImageUrl $sampleImageUrl = null,
        #[MapFromKey('sampleMovieURL')]
        public ?SampleMovieUrl $sampleMovieUrl = null,
        public ?ItemPrices $prices = null,
        public ?DateTimeImmutable $date = null,
        public ?ItemInfo $iteminfo = null,
    ) {
    }
}
