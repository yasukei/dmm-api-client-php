<?php

declare(strict_types=1);

namespace DmmApiClient\Exception;

use CuyZ\Valinor\Mapper\MappingError;
use RuntimeException;

/**
 * API レスポンスが期待する構造・型と一致しなかったことを表す例外。
 */
final class ResponseValidationException extends RuntimeException implements DmmApiClientException
{
    /**
     * @param class-string                       $targetClass マッピング先の DTO クラス
     * @param non-empty-list<array{path: string, message: string}> $errors      パスごとのエラー内容
     */
    private function __construct(
        string $message,
        public readonly string $targetClass,
        public readonly array $errors,
        MappingError $previous,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param class-string $targetClass
     */
    public static function fromMappingError(string $targetClass, MappingError $error): self
    {
        $errors = [];

        foreach ($error->messages()->errors() as $message) {
            $errors[] = [
                'path' => $message->path(),
                'message' => $message->toString(),
            ];
        }

        if ($errors === []) {
            $errors = [['path' => '*root*', 'message' => $error->getMessage()]];
        }

        $summary = implode(', ', array_map(
            static fn (array $e): string => "{$e['path']}: {$e['message']}",
            $errors,
        ));

        return new self(
            sprintf('Failed to validate DMM API response as %s. %s', $targetClass, $summary),
            $targetClass,
            $errors,
            $error,
        );
    }
}
