<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

/**
 * ジャンル・メーカー・女優などの ID と名称のペア。
 */
final readonly class ItemInfoElement
{
    /**
     * @param string $id   対象の ID
     * @param string $name 対象の名称
     */
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
