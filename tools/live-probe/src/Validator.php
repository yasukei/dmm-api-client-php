<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\Exception\ResponseValidationException;
use DmmApiClient\Response\ResponseMapper;

/**
 * 受け取ったボディを DTO へマッピングできるか確かめる。
 *
 * 失敗しても例外を投げず、パスごとのエラーとして返す。1 本の失敗で
 * 実行全体を止めず、最後にまとめてレポートにするため。
 */
final readonly class Validator
{
    public function __construct(
        private ResponseMapper $mapper = new ResponseMapper(),
    ) {
    }

    /**
     * @param class-string $responseClass
     *
     * @return list<array{path: string, message: string}> 空なら検証を通った
     */
    public function validate(string $responseClass, string $body): array
    {
        $decoded = Json::decode($body);

        if ($decoded === null) {
            return [['path' => '*json*', 'message' => 'Response body is not a JSON object.']];
        }

        try {
            $this->mapper->map($responseClass, $decoded);
        } catch (ResponseValidationException $exception) {
            return $exception->errors;
        }

        return [];
    }
}
