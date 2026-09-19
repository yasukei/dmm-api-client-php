<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapAsString;
use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 配信タイプごとの価格。
 *
 * {@see ItemPrices} と同じく、ゼロだけは数値で返ることがあるため、文字列に揃える。
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
        #[MapAsString]
        public string $price,
        #[MapFromKey('list_price')]
        #[MapAsString]
        public string $listPrice,
    ) {}
}
