<?php

declare(strict_types=1);

namespace DmmApiClient\Response\Common;

/**
 * レスポンスに含まれる、リクエストパラメータのエコーバック。
 *
 * パラメータ名をキーにした連想配列で返る。
 *
 * ```json
 * {"api_id": "xxx", "site": "DMM.com", "article": ["genre"], "article_id": ["15226"]}
 * ```
 *
 * 値は通常は文字列だが、`article` と `article_id` のように配列で送ったパラメータは
 * 配列のまま返ってくるため、文字列のリストも受け付ける。
 */
final readonly class RequestEcho
{
    /**
     * @param array<string, string|list<string>> $parameters API に送信したリクエストパラメータ
     */
    public function __construct(
        public array $parameters = [],
    ) {
    }
}
