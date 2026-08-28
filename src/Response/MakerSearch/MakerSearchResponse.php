<?php

declare(strict_types=1);

namespace DmmApiClient\Response\MakerSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * メーカー検索 API (`/MakerSearch`) のレスポンス。
 */
final readonly class MakerSearchResponse
{
    /**
     * @param MakerSearchResult $result  検索結果
     * @param RequestEcho|null  $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public MakerSearchResult $result,
        public ?RequestEcho $request = null,
    ) {
    }
}
