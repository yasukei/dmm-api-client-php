<?php

declare(strict_types=1);

namespace DmmApiClient\Response\FloorList;

/**
 * サービス情報と、それに属するフロアの一覧。
 */
final readonly class Service
{
    /**
     * @param string      $name  サービス名（例: 動画）
     * @param string      $code  サービスコード（例: digital）
     * @param list<Floor> $floor このサービスに属するフロアの一覧
     */
    public function __construct(
        public string $name,
        public string $code,
        public array $floor,
    ) {
    }
}
