<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * 商品のレビュー集計。
 */
final readonly class Review
{
    /**
     * @param int    $count   レビュー件数
     * @param string $average レビュー平均点（例: "4.20"）
     */
    public function __construct(
        public int $count,
        public string $average,
    ) {
    }
}
