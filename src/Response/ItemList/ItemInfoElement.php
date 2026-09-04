<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * ジャンル・メーカー・女優などの ID と名称のペア。
 */
final readonly class ItemInfoElement
{
    /**
     * ID はほぼ数値で返る。フロア検索が `floor_id` を文字列で返すのとは揃っていない。
     *
     * 数値にならない値もある。メーカーの「その他」枠は `"other"` という ID で返り、
     * これは実在のメーカーを指す ID ではなく、該当なしを表す区分。同種の枠が
     * 他の分類に現れても受け取れるよう、文字列も許す。
     *
     * @param int|string $id   対象の ID
     * @param string     $name 対象の名称
     */
    public function __construct(
        public int|string $id,
        public string $name,
    ) {
    }
}
