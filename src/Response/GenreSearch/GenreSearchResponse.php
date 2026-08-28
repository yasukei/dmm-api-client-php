<?php

declare(strict_types=1);

namespace DmmApiClient\Response\GenreSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * ジャンル検索 API (`/GenreSearch`) のレスポンス。
 */
final class GenreSearchResponse
{
    /**
     * @param GenreSearchResult $result  検索結果
     * @param RequestEcho|null  $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public readonly GenreSearchResult $result,
        public readonly ?RequestEcho $request = null,
    ) {
    }
}
