<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapAsString;

/**
 * ジャンル・メーカー・女優などの ID と名称のペア。
 */
final readonly class ItemInfoElement
{
    /**
     * ID はほぼ数値で返るが、他の API の ID に合わせて文字列に揃える。
     *
     * 数値にならない値もある。メーカーの「その他」枠は `"other"` という ID で返り、
     * これは実在のメーカーを指す ID ではなく、該当なしを表す区分。
     *
     * 読み仮名は人物系（女優・俳優・監督・作者・アーティスト）だけが持ち、ジャンルや
     * メーカーには付かない。持つ種類でも、電子書籍のフロアでは返らない。
     *
     * @param string      $id   対象の ID（例: "6533"、"other"）
     * @param string      $name 対象の名称
     * @param string|null $ruby 対象の名称かな
     */
    public function __construct(
        #[MapAsString]
        public string $id,
        public string $name,
        public ?string $ruby = null,
    ) {}
}
