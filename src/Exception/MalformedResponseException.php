<?php

declare(strict_types=1);

namespace DmmApiClient\Exception;

use JsonException;
use RuntimeException;

/**
 * レスポンスボディが JSON として読み取れなかったことを表す例外。
 */
final class MalformedResponseException extends RuntimeException implements DmmApiClientException
{
    private function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly string $responseBody,
        ?JsonException $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromJsonException(string $endpoint, string $responseBody, JsonException $previous): self
    {
        return new self(
            sprintf('Response from %s is not valid JSON: %s', $endpoint, $previous->getMessage()),
            $endpoint,
            $responseBody,
            $previous,
        );
    }

    public static function notAnObject(string $endpoint, string $responseBody): self
    {
        return new self(
            sprintf('Response from %s is valid JSON but not a JSON object.', $endpoint),
            $endpoint,
            $responseBody,
        );
    }
}
