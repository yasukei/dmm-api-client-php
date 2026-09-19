<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\Error;

use CuyZ\Valinor\Mapper\Configurator\MapAsString;

/**
 * エラーレスポンスの `result` 部。
 *
 * `errors` だけ省略可にしている。観測した限りでは必ず返っているが、必須にして外したときの
 * 代償が大きい。エラーボディの検証に失敗すると `ApiErrorException` は `$error` を持てず、
 * `status` も `message` も型として取り出せなくなる。空配列を既定にする側に代償は無い。
 */
final readonly class ErrorResult
{
    /**
     * @param string                $status  HTTP 相当のエラーステータスコード（例: "400"）
     * @param string                $message エラー内容（例: BAD REQUEST）
     * @param array<string, string> $errors  フィールド単位のエラー詳細（例: ["affiliate_id" => "Invalid Request Error"]）
     */
    public function __construct(
        #[MapAsString]
        public string $status,
        public string $message,
        public array $errors = [],
    ) {}
}
