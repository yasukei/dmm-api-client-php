<?php

declare(strict_types=1);

namespace DmmApiClient\Response\MakerSearch;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * メーカー検索 API (`/MakerSearch`) のレスポンス。
 */
final class MakerSearchResponse
{
    /**
     * @param MakerSearchResult $result  検索結果
     * @param RequestEcho|null  $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public readonly MakerSearchResult $result,
        public readonly ?RequestEcho $request = null,
    ) {
    }
}
