<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DateTimeImmutable;

/**
 * 商品情報 1 件。
 *
 * `title` と `URL` は、仕様上は必ず返ると期待したい項目だが、実際には欠けている商品が存在する。
 * 登録漏れと思われ、そうした商品では `affiliateURL` も `lurl=` が空のまま返ってくる。
 * 1 件の欠けたレコードでページ全体を取り落とさないよう、どちらも省略可能にしている。
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
     * @param string                 $affiliateUrl   商品ページのアフィリエイト URL
     * @param string|null            $title          商品タイトル。欠けている商品がまれにある
     * @param string|null            $url            商品ページの URL。欠けている商品がまれにある
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
        #[MapFromKey('affiliateURL')]
        public string $affiliateUrl,
        public ?string $title = null,
        #[MapFromKey('URL')]
        public ?string $url = null,
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
