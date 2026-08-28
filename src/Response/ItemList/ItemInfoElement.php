<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * ジャンル・メーカー・女優などの ID と名称のペア。
 */
final class ItemInfoElement
{
    /**
     * @param string $id   対象の ID
     * @param string $name 対象の名称
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }
}
