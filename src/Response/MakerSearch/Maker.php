<?php

declare(strict_types=1);

namespace DmmApiClient\Response\MakerSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * メーカー情報 1 件。
 */
final readonly class Maker
{
    /**
     * @param string $makerId メーカー ID（例: "306073"）
     * @param string $name    メーカー名
     * @param string $ruby    メーカー名かな
     * @param string $listUrl このメーカーの作品一覧へのアフィリエイトリンク
     */
    public function __construct(
        #[MapFromKey('maker_id')]
        public string $makerId,
        public string $name,
        public string $ruby,
        #[MapFromKey('list_url')]
        public string $listUrl,
    ) {
    }
}
