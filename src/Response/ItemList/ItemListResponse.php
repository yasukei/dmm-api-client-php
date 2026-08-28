<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use DmmApiClient\Response\Common\RequestEcho;

/**
 * 商品情報 API (`/ItemList`) のレスポンス。
 */
final class ItemListResponse
{
    /**
     * @param ItemListResult   $result  検索結果
     * @param RequestEcho|null $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public readonly ItemListResult $result,
        public readonly ?RequestEcho $request = null,
    ) {
    }
}
