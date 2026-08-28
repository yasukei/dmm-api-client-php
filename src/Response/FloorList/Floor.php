<?php

declare(strict_types=1);

namespace DmmApiClient\Response\FloorList;

/**
 * フロア情報。
 */
final readonly class Floor
{
    /**
     * @param string $id   フロア ID（例: "43"）
     * @param string $name フロア名（例: ビデオ）
     * @param string $code フロアコード（例: videoa）
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
    ) {
    }
}
