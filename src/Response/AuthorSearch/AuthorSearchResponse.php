<?php

declare(strict_types=1);

namespace DmmApiClient\Response\AuthorSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * 作者検索 API (`/AuthorSearch`) のレスポンス。
 */
final class AuthorSearchResponse
{
    /**
     * @param AuthorSearchResult $result  検索結果
     * @param RequestEcho|null   $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public readonly AuthorSearchResult $result,
        public readonly ?RequestEcho $request = null,
    ) {
    }
}
