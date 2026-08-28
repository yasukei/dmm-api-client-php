<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * 配信タイプ別価格の一覧。
 */
final readonly class Deliveries
{
    /**
     * @param list<Delivery> $delivery 配信タイプごとの価格
     */
    public function __construct(
        public array $delivery = [],
    ) {
    }
}
