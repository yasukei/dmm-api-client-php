<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 配信タイプごとの価格。
 */
final readonly class Delivery
{
    /**
     * @param string $type      配信タイプ（例: basket、download、stream、4k）
     * @param string $price     販売価格（例: "2000"）
     * @param string $listPrice 定価（例: "3000"）
     */
    public function __construct(
        public string $type,
        public string $price,
        #[MapFromKey('list_price')]
        public string $listPrice,
    ) {
    }
}
