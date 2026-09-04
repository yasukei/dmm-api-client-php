<?php

declare(strict_types=1);

namespace DmmApiClient\Response\SeriesSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * シリーズ情報 1 件。
 *
 * `list_url` はキーごと返らないことがある。一覧ページを持たないフロアがあり、
 * 同じ ID でもフロアによって返る・返らないが変わる。
 */
final readonly class Series
{
    /**
     * @param string      $seriesId シリーズ ID（例: "62226"）
     * @param string      $name     シリーズ名
     * @param string      $ruby     シリーズ名かな
     * @param string|null $listUrl  このシリーズの作品一覧へのアフィリエイトリンク
     */
    public function __construct(
        #[MapFromKey('series_id')]
        public string $seriesId,
        public string $name,
        public string $ruby,
        #[MapFromKey('list_url')]
        public ?string $listUrl = null,
    ) {
    }
}
