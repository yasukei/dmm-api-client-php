<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 商品の価格情報。
 *
 * 価格は文字列で返るが、ゼロだけは数値の `0` で返ることがある。書き分けは一貫しておらず、
 * 同じ商品で `price` が `"0"`、`list_price` が `0` になることもある。
 */
final readonly class ItemPrices
{
    /**
     * @param int|string      $price      販売価格（例: "2000"。ゼロは "0" と 0 のどちらもある）
     * @param int|string|null $listPrice  定価（販売価格と異なる場合、例: "3000"）
     * @param Deliveries|null $deliveries 配信タイプ別の価格
     */
    public function __construct(
        public int|string $price,
        #[MapFromKey('list_price')]
        public int|string|null $listPrice = null,
        public ?Deliveries $deliveries = null,
    ) {
    }
}
