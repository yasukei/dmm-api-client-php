<?php

declare(strict_types=1);

namespace Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * PSR-18 クライアントが通信に失敗したときに投げる例外の代役。
 */
final class NetworkFailure extends RuntimeException implements ClientExceptionInterface
{
}
