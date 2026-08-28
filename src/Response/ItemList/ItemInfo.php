<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * 商品に紐づく分類情報。
 *
 * いずれもレスポンスに含まれない場合があり、その場合は空配列となる。
 */
final readonly class ItemInfo
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
        public array $genre = [],
        public array $series = [],
        public array $maker = [],
        public array $actor = [],
        public array $actress = [],
        public array $director = [],
        public array $author = [],
        public array $label = [],
        public array $type = [],
        public array $color = [],
        public array $size = [],
    ) {
    }
}
