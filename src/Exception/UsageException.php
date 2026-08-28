<?php

declare(strict_types=1);

namespace DmmApiClient\Exception;

use RuntimeException;

/**
 * コマンドラインの指定に誤りがあることを表す例外。
 */
final class UsageException extends RuntimeException implements DmmApiClientException
{
}
