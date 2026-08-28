<?php

declare(strict_types=1);

namespace DmmApiClient\Response\Common;

/**
 * レスポンスに含まれる、リクエストパラメータのエコーバック。
 *
 * `parameters` は 2 つの形式のいずれかで返る。
 *  - 連想配列形式:       {"api_id": "xxx", "output": "json"}
 *  - name/value 配列形式: [{"name": "api_id", "value": "xxx"}]
 */
final class RequestEcho
{
    /**
     * @param array<string, string>|non-empty-list<RequestParameter> $parameters API に送信したリクエストパラメータ
     */
    public function __construct(
        public readonly array $parameters = [],
    ) {
    }
}
