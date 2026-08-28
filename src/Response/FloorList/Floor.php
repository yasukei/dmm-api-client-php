<?php

declare(strict_types=1);

namespace DmmApiClient\Response\FloorList;

/**
 * フロア情報。
 */
final class Floor
{
    /**
     * @param string $id   フロア ID（例: "43"）
     * @param string $name フロア名（例: ビデオ）
     * @param string $code フロアコード（例: videoa）
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $code,
    ) {
    }
}
