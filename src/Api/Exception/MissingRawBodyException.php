<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Exception;

use LogicException;

/**
 * {@see \DmmApiClient\Api\DmmApiClient} を経由せずに作った DTO から、生ボディを読もうとしたことを表す例外。
 */
final class MissingRawBodyException extends LogicException implements DmmApiClientException
{
    /**
     * @param class-string $dtoClass
     */
    public static function for(string $dtoClass): self
    {
        return new self($dtoClass . ' was constructed without a raw body attached (bypassed DmmApiClient).');
    }
}
