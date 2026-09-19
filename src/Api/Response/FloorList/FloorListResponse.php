<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\FloorList;

use DmmApiClient\Api\Response\Common\RawBodyAware;
use DmmApiClient\Api\Response\Common\RequestEcho;

/**
 * フロア検索 API (`/FloorList`) のレスポンス。
 */
final readonly class FloorListResponse
{
    use RawBodyAware;

    /**
     * @param FloorListResult  $result  フロア構成
     * @param RequestEcho|null $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public FloorListResult $result,
        public ?RequestEcho $request = null,
    ) {}
}
