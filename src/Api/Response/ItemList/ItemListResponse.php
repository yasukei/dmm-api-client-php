<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

use DmmApiClient\Api\Response\Common\RawBodyAware;
use DmmApiClient\Api\Response\Common\RequestEcho;

/**
 * 商品情報 API (`/ItemList`) のレスポンス。
 */
final readonly class ItemListResponse
{
    use RawBodyAware;

    /**
     * @param ItemListResult   $result  検索結果
     * @param RequestEcho|null $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public ItemListResult $result,
        public ?RequestEcho $request = null,
    ) {}
}
