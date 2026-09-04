<?php

declare(strict_types=1);

namespace DmmApiClient\Response\GenreSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DmmApiClient\SiteCode;

/**
 * ジャンル検索 API のレスポンスの `result` 部。
 *
 * `status` は文字列で返る。`total_count` は 0 件のときだけ数値で、それ以外は文字列。
 * 商品情報 API はいずれも数値で返すので、揃っていない。
 */
final readonly class GenreSearchResult
{
    /**
     * @param string      $status        ステータスコード
     * @param int         $resultCount   このレスポンスに含まれる件数
     * @param int|string  $totalCount    検索結果の総件数（0 件のときだけ数値）
     * @param int         $firstPosition 検索開始位置（1 始まり）
     * @param string      $siteName      サイト名（例: DMM.com（一般））
     * @param SiteCode    $siteCode      サイトコード
     * @param string      $serviceName   サービス名（例: 動画）
     * @param string      $serviceCode   サービスコード（例: digital）
     * @param string      $floorId       フロア ID（例: "43"）
     * @param string      $floorName     フロア名（例: ビデオ）
     * @param string      $floorCode     フロアコード（例: videoa）
     * @param list<Genre> $genre         検索結果のジャンル一覧
     */
    public function __construct(
        public string $status,
        #[MapFromKey('result_count')]
        public int $resultCount,
        #[MapFromKey('total_count')]
        public int|string $totalCount,
        #[MapFromKey('first_position')]
        public int $firstPosition,
        #[MapFromKey('site_name')]
        public string $siteName,
        #[MapFromKey('site_code')]
        public SiteCode $siteCode,
        #[MapFromKey('service_name')]
        public string $serviceName,
        #[MapFromKey('service_code')]
        public string $serviceCode,
        #[MapFromKey('floor_id')]
        public string $floorId,
        #[MapFromKey('floor_name')]
        public string $floorName,
        #[MapFromKey('floor_code')]
        public string $floorCode,
        public array $genre = [],
    ) {
    }
}
