<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\FloorList;

/**
 * サイト情報と、それに属するサービスの一覧。
 */
final readonly class Site
{
    /**
     * @param string        $name    サイト名（例: DMM.com（一般））
     * @param string        $code    サイトコード（例: DMM.com、FANZA）
     * @param list<Service> $service このサイトに属するサービスの一覧
     */
    public function __construct(
        public string $name,
        public string $code,
        public array $service,
    ) {
    }
}
