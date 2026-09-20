<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapAsFloat;

/**
 * 商品のレビュー集計。
 *
 * 平均点は文字列で返るが、小数第 2 位までの数値しか現れないため float に揃える。
 */
final readonly class Review
{
    /**
     * @param int   $count   レビュー件数
     * @param float $average レビュー平均点（0.0〜5.0）。$count が 0 のときは 0.0
     */
    public function __construct(
        public int $count,
        #[MapAsFloat]
        public float $average,
    ) {}
}
