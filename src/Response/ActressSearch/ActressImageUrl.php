<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

/**
 * 女優のプロフィール画像の URL。
 */
final readonly class ActressImageUrl
{
    /**
     * @param string $small 小サイズ画像の URL
     * @param string $large 大サイズ画像の URL
     */
    public function __construct(
        public string $small,
        public string $large,
    ) {
    }
}
