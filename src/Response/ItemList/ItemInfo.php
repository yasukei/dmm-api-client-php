<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * 商品に紐づく分類情報。
 *
 * いずれもレスポンスに含まれない場合があり、その場合は空配列となる。
 */
final class ItemInfo
{
    /**
     * @param list<ItemInfoElement> $genre    ジャンル
     * @param list<ItemInfoElement> $series   シリーズ
     * @param list<ItemInfoElement> $maker    メーカー
     * @param list<ItemInfoElement> $actor    男優
     * @param list<ItemInfoElement> $actress  女優
     * @param list<ItemInfoElement> $director 監督
     * @param list<ItemInfoElement> $author   作者
     * @param list<ItemInfoElement> $label    レーベル
     * @param list<ItemInfoElement> $type     タイプ
     * @param list<ItemInfoElement> $color    カラー
     * @param list<ItemInfoElement> $size     サイズ
     */
    public function __construct(
        public readonly array $genre = [],
        public readonly array $series = [],
        public readonly array $maker = [],
        public readonly array $actor = [],
        public readonly array $actress = [],
        public readonly array $director = [],
        public readonly array $author = [],
        public readonly array $label = [],
        public readonly array $type = [],
        public readonly array $color = [],
        public readonly array $size = [],
    ) {
    }
}
