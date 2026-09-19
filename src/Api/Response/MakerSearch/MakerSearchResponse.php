<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\MakerSearch;

use DmmApiClient\Api\Response\Common\RawBodyAware;
use DmmApiClient\Api\Response\Common\RequestEcho;

/**
 * メーカー検索 API (`/MakerSearch`) のレスポンス。
 */
final readonly class MakerSearchResponse
{
    use RawBodyAware;

    /**
     * @param MakerSearchResult $result  検索結果
     * @param RequestEcho|null  $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public MakerSearchResult $result,
        public ?RequestEcho $request = null,
    ) {}
}
