<?php

declare(strict_types=1);

namespace DmmApiClient\Response\FloorList;

use DmmApiClient\SiteCode;

/**
 * サイト情報と、それに属するサービスの一覧。
 */
final readonly class Site
{
    /**
     * @param string        $name    サイト名（例: DMM.com（一般））
     * @param SiteCode      $code    サイトコード
     * @param list<Service> $service このサイトに属するサービスの一覧
     */
    public function __construct(
        public string $name,
        public SiteCode $code,
        public array $service,
    ) {
    }
}
