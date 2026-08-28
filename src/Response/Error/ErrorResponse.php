<?php

declare(strict_types=1);

namespace DmmApiClient\Response\Error;

/**
 * API がエラーを返した場合のレスポンス。
 */
final readonly class ErrorResponse
{
    /**
     * @param ErrorResult $result エラー内容
     */
    public function __construct(
        public ErrorResult $result,
    ) {
    }
}
