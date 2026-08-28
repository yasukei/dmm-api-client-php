<?php

declare(strict_types=1);

namespace DmmApiClient\Response\AuthorSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * 作者検索 API (`/AuthorSearch`) のレスポンス。
 */
final readonly class AuthorSearchResponse
{
    /**
     * @param AuthorSearchResult $result  検索結果
     * @param RequestEcho|null   $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public AuthorSearchResult $result,
        public ?RequestEcho $request = null,
    ) {
    }
}
