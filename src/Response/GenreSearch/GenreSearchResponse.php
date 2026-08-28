<?php

declare(strict_types=1);

namespace DmmApiClient\Response\GenreSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * ジャンル検索 API (`/GenreSearch`) のレスポンス。
 */
final readonly class GenreSearchResponse
{
    /**
     * @param GenreSearchResult $result  検索結果
     * @param RequestEcho|null  $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public GenreSearchResult $result,
        public ?RequestEcho $request = null,
    ) {
    }
}
