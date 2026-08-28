<?php

declare(strict_types=1);

namespace DmmApiClient\Response\MakerSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DmmApiClient\SiteCode;

/**
 * メーカー検索 API のレスポンスの `result` 部。
 */
final class MakerSearchResult
{
    /**
     * @param int         $status        ステータスコード
     * @param int         $resultCount   このレスポンスに含まれる件数
     * @param int         $totalCount    検索結果の総件数
     * @param int         $firstPosition 検索開始位置（1 始まり）
     * @param string      $siteName      サイト名（例: DMM.com（一般））
     * @param SiteCode    $siteCode      サイトコード
     * @param string      $serviceName   サービス名（例: 動画）
     * @param string      $serviceCode   サービスコード（例: digital）
     * @param string      $floorId       フロア ID（例: "43"）
     * @param string      $floorName     フロア名（例: ビデオ）
     * @param string      $floorCode     フロアコード（例: videoa）
     * @param list<Maker> $maker         検索結果のメーカー一覧
     */
    public function __construct(
        public readonly int $status,
        #[MapFromKey('result_count')]
        public readonly int $resultCount,
        #[MapFromKey('total_count')]
        public readonly int $totalCount,
        #[MapFromKey('first_position')]
        public readonly int $firstPosition,
        #[MapFromKey('site_name')]
        public readonly string $siteName,
        #[MapFromKey('site_code')]
        public readonly SiteCode $siteCode,
        #[MapFromKey('service_name')]
        public readonly string $serviceName,
        #[MapFromKey('service_code')]
        public readonly string $serviceCode,
        #[MapFromKey('floor_id')]
        public readonly string $floorId,
        #[MapFromKey('floor_name')]
        public readonly string $floorName,
        #[MapFromKey('floor_code')]
        public readonly string $floorCode,
        public readonly array $maker = [],
    ) {
    }
}
