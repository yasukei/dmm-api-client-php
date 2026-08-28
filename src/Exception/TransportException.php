<?php

declare(strict_types=1);

namespace DmmApiClient\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * HTTP 通信自体に失敗したことを表す例外。
 *
 * 名前解決の失敗、接続タイムアウトなど、レスポンスを受け取れなかった場合に送出される。
 */
final class TransportException extends RuntimeException implements DmmApiClientException
{
    private function __construct(
        string $message,
        public readonly string $endpoint,
        ClientExceptionInterface $previous,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromClientException(string $endpoint, ClientExceptionInterface $previous): self
    {
        return new self(
            sprintf('HTTP request to %s failed: %s', $endpoint, $previous->getMessage()),
            $endpoint,
            $previous,
        );
    }
}
