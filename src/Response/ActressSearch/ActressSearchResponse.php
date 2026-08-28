<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * 女優検索 API (`/ActressSearch`) のレスポンス。
 */
final readonly class ActressSearchResponse
{
    /**
     * @param ActressSearchResult $result  検索結果
     * @param RequestEcho|null    $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public ActressSearchResult $result,
        public ?RequestEcho $request = null,
    ) {
    }
}
