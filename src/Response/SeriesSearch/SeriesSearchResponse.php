<?php

declare(strict_types=1);

namespace DmmApiClient\Response\SeriesSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * シリーズ検索 API (`/SeriesSearch`) のレスポンス。
 */
final readonly class SeriesSearchResponse
{
    /**
     * @param SeriesSearchResult $result  検索結果
     * @param RequestEcho|null   $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public SeriesSearchResult $result,
        public ?RequestEcho $request = null,
    ) {
    }
}
