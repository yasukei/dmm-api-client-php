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
     * 読み仮名は人物系（女優・男優・監督・作者・アーティスト）だけが持ち、ジャンルや
     * メーカーには付かない。持つ種類でも、電子書籍のフロアでは返らない。
     *
     * @param int|string  $id   対象の ID
     * @param string      $name 対象の名称
     * @param string|null $ruby 対象の名称かな
     */
    public function __construct(
        public int|string $id,
        public string $name,
        public ?string $ruby = null,
    ) {
    }
}
