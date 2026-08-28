<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * 商品画像の URL。
 */
final class ItemImageUrl
{
    /**
     * @param string      $list  一覧表示用画像の URL
     * @param string|null $small 小サイズ画像の URL
     * @param string|null $large 大サイズ画像の URL
     */
    public function __construct(
        public readonly string $list,
        public readonly ?string $small = null,
        public readonly ?string $large = null,
    ) {
    }
}
