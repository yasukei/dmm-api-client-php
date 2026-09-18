<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\Error;

use DmmApiClient\Api\Response\Common\RequestEcho;

/**
 * API がエラーを返した場合のレスポンス。
 */
final readonly class ErrorResponse
{
    /**
     * @param ErrorResult      $result  エラー内容
     * @param RequestEcho|null $request 送信したリクエストパラメータのエコーバック
     */
    public function __construct(
        public ErrorResult $result,
        public ?RequestEcho $request = null,
    ) {}
}
