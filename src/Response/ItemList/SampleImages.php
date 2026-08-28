<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * サンプル画像 URL の一覧。
 */
final class SampleImages
{
    /**
     * @param list<string> $image サンプル画像の URL
     */
    public function __construct(
        public readonly array $image = [],
    ) {
    }
}
