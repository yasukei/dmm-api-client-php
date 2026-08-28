<?php

declare(strict_types=1);

namespace DmmApiClient\Exception;

use DmmApiClient\Response\Error\ErrorResult;
use RuntimeException;

/**
 * API がエラーレスポンスを返したことを表す例外。
 *
 * エラーボディを {@see ErrorResult} として読み取れた場合は `$error` に格納される。
 * 読み取れなかった場合は `$error` が null になり、生のボディが `$responseBody` に入る。
 */
final class ApiErrorException extends RuntimeException implements DmmApiClientException
{
    private function __construct(
        string $message,
        public readonly int $httpStatusCode,
        public readonly ?ErrorResult $error,
        public readonly string $responseBody,
    ) {
        parent::__construct($message, $httpStatusCode);
    }

    public static function fromErrorResult(int $httpStatusCode, ErrorResult $error, string $responseBody): self
    {
        $detail = $error->errors === []
            ? ''
            : ' (' . implode(', ', array_map(
                static fn (string $field, string $reason): string => "{$field}: {$reason}",
                array_keys($error->errors),
                $error->errors,
            )) . ')';

        return new self(
            sprintf('DMM API returned %d %s%s', $error->status, $error->message, $detail),
            $httpStatusCode,
            $error,
            $responseBody,
        );
    }

    public static function fromUnreadableBody(int $httpStatusCode, string $responseBody): self
    {
        return new self(
            sprintf('DMM API returned HTTP %d with an unrecognized error body.', $httpStatusCode),
            $httpStatusCode,
            null,
            $responseBody,
        );
    }
}
