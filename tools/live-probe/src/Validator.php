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
    private ResponseMapper $strict;

    /**
     * @param ResponseMapper      $mapper 通常の検証に使うマッパー
     * @param ResponseMapper|null $strict 知らないキーの検知に使うマッパー
     */
    public function __construct(
        private ResponseMapper $mapper = new ResponseMapper(),
        ?ResponseMapper $strict = null,
    ) {
        $this->strict = $strict ?? ResponseMapper::strict();
    }

    /**
     * @param class-string $responseClass
     *
     * @return list<array{path: string, message: string}> 空なら検証を通った
     */
    public function validate(string $responseClass, string $body): array
    {
        return self::strip($this->map($this->mapper, $responseClass, $body));
    }

    /**
     * DTO が知らないキーを探す。
     *
     * 既定のマッパーは知らないキーを黙って捨てるので、DMM 側で項目が増えても
     * 検証は通ってしまう。ここだけ厳密なマッパーで読み直して、増えた項目を拾う。
     *
     * 厳密なマッパーは型の食い違いも同じ例外で返すが、それは {@see self::validate()} が
     * 既に報告している。二重に数えないよう、知らないキーのエラーだけを取り出す。
     *
     * @param class-string $responseClass
     *
     * @return list<array{path: string, message: string}> 空なら知らないキーは無かった
     */
    public function unknownKeys(string $responseClass, string $body): array
    {
        return self::strip(array_filter(
            $this->map($this->strict, $responseClass, $body),
            static fn (array $error): bool => $error['code'] === ResponseValidationException::CODE_UNEXPECTED_KEY,
        ));
    }

    /**
     * レポートに要らないコードを落とす。
     *
     * @param iterable<array{path: string, message: string, code: string}> $errors
     *
     * @return list<array{path: string, message: string}>
     */
    private static function strip(iterable $errors): array
    {
        $stripped = [];

        foreach ($errors as $error) {
            $stripped[] = ['path' => $error['path'], 'message' => $error['message']];
        }

        return $stripped;
    }

    /**
     * @param class-string $responseClass
     *
     * @return list<array{path: string, message: string, code: string}>
     */
    private function map(ResponseMapper $mapper, string $responseClass, string $body): array
    {
        $decoded = Json::decode($body);

        if ($decoded === null) {
            return [['path' => '*json*', 'message' => 'Response body is not a JSON object.', 'code' => 'invalid_json']];
        }

        try {
            $mapper->map($responseClass, $decoded);
        } catch (ResponseValidationException $exception) {
            return $exception->errors;
        }

        return [];
    }
}
