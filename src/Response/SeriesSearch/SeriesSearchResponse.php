<?php

declare(strict_types=1);

namespace DmmApiClient\Response\SeriesSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * シリーズ検索 API (`/SeriesSearch`) のレスポンス。
 */
final class SeriesSearchResponse
{
    /**
     * @param SeriesSearchResult $result  検索結果
     * @param RequestEcho|null   $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public readonly SeriesSearchResult $result,
        public readonly ?RequestEcho $request = null,
    ) {
    }
}
