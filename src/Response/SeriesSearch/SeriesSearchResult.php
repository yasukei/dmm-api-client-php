<?php

declare(strict_types=1);

namespace DmmApiClient\Response\SeriesSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DmmApiClient\SiteCode;

/**
 * シリーズ検索 API のレスポンスの `result` 部。
 *
 * `status` は文字列で返る。商品情報 API が数値で返すのとは揃っていない。
 */
final readonly class SeriesSearchResult
{
    /**
     * @param string       $status        ステータスコード
     * @param int          $resultCount   このレスポンスに含まれる件数
     * @param int          $totalCount    検索結果の総件数
     * @param int          $firstPosition 検索開始位置（1 始まり）
     * @param string       $siteName      サイト名（例: DMM.com（一般））
     * @param SiteCode     $siteCode      サイトコード
     * @param string       $serviceName   サービス名（例: 動画）
     * @param string       $serviceCode   サービスコード（例: digital）
     * @param string       $floorId       フロア ID（例: "43"）
     * @param string       $floorName     フロア名（例: ビデオ）
     * @param string       $floorCode     フロアコード（例: videoa）
     * @param list<Series> $series        検索結果のシリーズ一覧
     */
    public function __construct(
        public string $status,
        #[MapFromKey('result_count')]
        public int $resultCount,
        #[MapFromKey('total_count')]
        public int $totalCount,
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
        public array $series = [],
    ) {
    }
}
