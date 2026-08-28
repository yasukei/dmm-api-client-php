<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 立ち読みページへのリンク。
 */
final readonly class Tachiyomi
{
    /**
     * @param string $url          立ち読みページの URL
     * @param string $affiliateUrl 立ち読みページのアフィリエイト URL
     */
    public function __construct(
        #[MapFromKey('URL')]
        public string $url,
        #[MapFromKey('affiliateURL')]
        public string $affiliateUrl,
    ) {
    }
}
