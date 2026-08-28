<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * 商品画像の URL。
 */
final readonly class ItemImageUrl
{
    /**
     * @param string      $list  一覧表示用画像の URL
     * @param string|null $small 小サイズ画像の URL
     * @param string|null $large 大サイズ画像の URL
     */
    public function __construct(
        public string $list,
        public ?string $small = null,
        public ?string $large = null,
    ) {
    }
}
