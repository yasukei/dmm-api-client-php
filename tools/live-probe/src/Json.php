<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use JsonException;

/**
 * JSON の読み書き。probe が扱うのは「配列になるはずの JSON」だけなので、
 * それ以外は null を返して呼び出し側で失敗として扱えるようにしている。
 */
final class Json
{
    /**
     * @return array<mixed>|null JSON として読めない、または配列でない場合は null
     */
    public static function decode(string $body): ?array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function encode(array $data, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($data, $flags);
    }

    /**
     * 配列とは限らない値を、そのまま JSON の 1 行にする。
     */
    public static function encodeValue(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * レスポンスの `result` 配下から整数値を取り出す。
     *
     * DTO を通さずに読むのは、検証に失敗するレスポンスからも件数を拾って
     * 次のページ位置を決めたいため。
     *
     * @param array<mixed> $decoded
     */
    public static function resultInt(array $decoded, string $key): ?int
    {
        $result = $decoded['result'] ?? null;

        if (! is_array($result)) {
            return null;
        }

        $value = $result[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : null;
    }
}
