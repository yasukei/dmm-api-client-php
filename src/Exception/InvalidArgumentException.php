<?php

declare(strict_types=1);

namespace DmmApiClient\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

/**
 * リクエストの組み立て時に、不正な値が渡されたことを表す例外。
 */
final class InvalidArgumentException extends BaseInvalidArgumentException implements DmmApiClientException
{
}
