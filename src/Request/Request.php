<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

/**
 * 各 API へのリクエストパラメータを表す。
 *
 * 認証情報（`api_id` / `affiliate_id`）と `output` は各リクエストには含めず、
 * {@see Credentials} と呼び出し側のクライアントが付与する。
 */
interface Request
{
    /**
     * API のエンドポイントパス（例: `/ItemList`）。
     */
    public function endpoint(): string;

    /**
     * クエリ文字列に載せるパラメータ。未指定の項目は含まれない。
     *
     * 値が `list<string>` の項目は、`http_build_query()` によって
     * `article[0]=genre&article[1]=actress` の形式に展開される。
     *
     * @return array<string, string|list<string>>
     */
    public function toQueryParameters(): array;
}
