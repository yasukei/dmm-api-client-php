<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * ジャンル・メーカー・女優などの ID と名称のペア。
 */
final readonly class ItemInfoElement
{
    /**
     * ID は数値で返る。フロア検索が `floor_id` を文字列で返すのとは揃っていない。
     *
     * @param int    $id   対象の ID
     * @param string $name 対象の名称
     */
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }
}
