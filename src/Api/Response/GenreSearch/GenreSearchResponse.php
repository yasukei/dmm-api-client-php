<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\GenreSearch;

use DmmApiClient\Api\Response\Common\RawBodyAware;
use DmmApiClient\Api\Response\Common\RequestEcho;

/**
 * ジャンル検索 API (`/GenreSearch`) のレスポンス。
 */
final readonly class GenreSearchResponse
{
    use RawBodyAware;

    /**
     * @param GenreSearchResult $result  検索結果
     * @param RequestEcho|null  $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public GenreSearchResult $result,
        public ?RequestEcho $request = null,
    ) {}
}
