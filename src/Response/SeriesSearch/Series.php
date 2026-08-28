<?php

declare(strict_types=1);

namespace DmmApiClient\Response\SeriesSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * シリーズ情報 1 件。
 */
final class Series
{
    /**
     * @param string $seriesId シリーズ ID（例: "62226"）
     * @param string $name     シリーズ名
     * @param string $ruby     シリーズ名かな
     * @param string $listUrl  このシリーズの作品一覧へのアフィリエイトリンク
     */
    public function __construct(
        #[MapFromKey('series_id')]
        public readonly string $seriesId,
        public readonly string $name,
        public readonly string $ruby,
        #[MapFromKey('list_url')]
        public readonly string $listUrl,
    ) {
    }
}
