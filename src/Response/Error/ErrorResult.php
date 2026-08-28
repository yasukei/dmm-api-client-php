<?php

declare(strict_types=1);

namespace DmmApiClient\Response\Error;

/**
 * エラーレスポンスの `result` 部。
 */
final readonly class ErrorResult
{
    /**
     * @param int                   $status  HTTP 相当のエラーステータスコード（例: 400）
     * @param string                $message エラー内容（例: BAD REQUEST）
     * @param array<string, string> $errors  フィールド単位のエラー詳細（例: ["affiliate_id" => "Invalid Request Error"]）
     */
    public function __construct(
        public int $status,
        public string $message,
        public array $errors = [],
    ) {
    }
}
