<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapAsString;
use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 商品の価格情報。
 *
 * 価格は文字列で返るが、ゼロだけは数値の `0` で返ることがある。書き分けは一貫しておらず、
 * 同じ商品で `price` が `"0"`、`list_price` が `0` になることもある。`"300~"` のような
 * 数値でない値もあるため、文字列に揃える。
 */
final readonly class ItemPrices
{
    /**
     * @param string          $price      販売価格（例: "2000"、"300~"）
     * @param string|null     $listPrice  定価（販売価格と異なる場合、例: "3000"）
     * @param Deliveries|null $deliveries 配信タイプ別の価格
     */
    public function __construct(
        #[MapAsString]
        public string $price,
        #[MapFromKey('list_price')]
        #[MapAsString]
        public ?string $listPrice = null,
        public ?Deliveries $deliveries = null,
    ) {}
}
