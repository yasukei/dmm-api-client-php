<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 商品の価格情報。
 */
final readonly class ItemPrices
{
    /**
     * @param string          $price      販売価格（例: "2000"）
     * @param string|null     $listPrice  定価（販売価格と異なる場合、例: "3000"）
     * @param Deliveries|null $deliveries 配信タイプ別の価格
     */
    public function __construct(
        public string $price,
        #[MapFromKey('list_price')]
        public ?string $listPrice = null,
        public ?Deliveries $deliveries = null,
    ) {
    }
}
