<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

/**
 * 通販（mono）のフロアが返す、商品階層の 1 段分。
 *
 * {@see Item::$directory} には上位階層から下位階層の順で並ぶ
 * （例: DVD → イメージビデオ → 女性アイドル・グラビア）。
 */
final readonly class Directory
{
    /**
     * @param int    $id   商品階層の ID（例: 102）
     * @param string $name 商品階層の名称（例: DVD）
     */
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
