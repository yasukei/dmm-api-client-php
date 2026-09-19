<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Exception;

use CuyZ\Valinor\Mapper\MappingError;
use RuntimeException;

/**
 * API レスポンスが期待する構造・型と一致しなかったことを表す例外。
 */
final class ResponseValidationException extends RuntimeException implements DmmApiClientException
{
    /**
     * レスポンスに DTO の知らないキーが含まれていたことを表すコード。
     *
     * このエラーが出るのは、知らないキーを許さないマッパーを使った場合だけ。
     * {@see \DmmApiClient\Api\Response\ResponseMapper::strictMapperBuilder()}
     */
    public const string CODE_UNEXPECTED_KEY = 'unexpected_key';

    /**
     * @param class-string                                                       $targetClass  マッピング先の DTO クラス
     * @param non-empty-list<array{path: string, message: string, code: string}> $errors       パスごとのエラー内容
     * @param string|null                                                        $responseBody 受け取ったままの生ボディ。{@see \DmmApiClient\Api\DmmApiClient} を経由せずにマッピングした場合は null
     */
    private function __construct(
        string $message,
        public readonly string $targetClass,
        public readonly array $errors,
        MappingError $previous,
        public readonly ?string $responseBody = null,
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
                // 文言ではなくコードで種類を判別できるようにしておく。
                'code' => $message->code(),
            ];
        }

        if ($errors === []) {
            $errors = [['path' => '*root*', 'message' => $error->getMessage(), 'code' => 'unknown']];
        }

        $summary = implode(', ', array_map(
            static fn(array $e): string => "{$e['path']}: {$e['message']}",
            $errors,
        ));

        return new self(
            sprintf('Failed to validate DMM API response as %s. %s', $targetClass, $summary),
            $targetClass,
            $errors,
            $error,
        );
    }

    /**
     * 生ボディを添えた例外を返す。
     *
     * マッピングはデコード済みの配列に対して行うため、生ボディはマッピングの外でしか分からない。
     *
     * @internal {@see \DmmApiClient\Api\DmmApiClient} が使う。
     */
    public function withResponseBody(string $responseBody): self
    {
        $previous = $this->getPrevious();
        assert($previous instanceof MappingError);

        return new self($this->getMessage(), $this->targetClass, $this->errors, $previous, $responseBody);
    }
}
