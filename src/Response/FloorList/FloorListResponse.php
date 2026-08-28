<?php

declare(strict_types=1);

namespace DmmApiClient\Response\FloorList;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * フロア検索 API (`/FloorList`) のレスポンス。
 */
final readonly class FloorListResponse
{
    /**
     * @param FloorListResult  $result  フロア構成
     * @param RequestEcho|null $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public FloorListResult $result,
        public ?RequestEcho $request = null,
    ) {
    }
}
