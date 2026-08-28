<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * `tests/Fixtures` 配下の JSON を読み込む。
 */
final class Fixture
{
    public static function path(string $name): string
    {
        return __DIR__ . '/../Fixtures/' . $name . '.json';
    }

    public static function json(string $name): string
    {
        $contents = file_get_contents(self::path($name));

        if ($contents === false) {
            throw new RuntimeException(sprintf('Fixture "%s" could not be read.', $name));
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>
     */
    public static function decoded(string $name): array
    {
        $decoded = json_decode(self::json($name), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('Fixture "%s" is not a JSON object.', $name));
        }

        /** @var array<string, mixed> */
        return $decoded;
    }

    /**
     * 指定したパスの値を差し替えたペイロードを返す。
     *
     * @param non-empty-list<string|int> $path  ネストしたキーの並び（例: ['result', 'total_count']）
     * @param mixed                      $value 差し替える値
     *
     * @return array<string, mixed>
     */
    public static function decodedWith(string $name, array $path, mixed $value): array
    {
        $payload = self::decoded($name);
        $cursor = &self::locate($payload, $path, array_key_last($path));

        $cursor = $value;

        return $payload;
    }

    /**
     * 指定したパスの値を取り除いたペイロードを返す。
     *
     * @param non-empty-list<string|int> $path ネストしたキーの並び
     *
     * @return array<string, mixed>
     */
    public static function decodedWithout(string $name, array $path): array
    {
        $payload = self::decoded($name);
        $lastIndex = array_key_last($path);
        $parent = &self::locate($payload, $path, $lastIndex - 1);

        if (! is_array($parent)) {
            throw new RuntimeException(sprintf('Path %s does not point inside an array.', implode('.', $path)));
        }

        unset($parent[$path[$lastIndex]]);

        return $payload;
    }

    /**
     * $path の先頭から $upTo 番目までたどり、その位置への参照を返す。
     *
     * @param array<string, mixed>       $payload
     * @param non-empty-list<string|int> $path
     */
    private static function &locate(array &$payload, array $path, int $upTo): mixed
    {
        $cursor = &$payload;

        for ($index = 0; $index <= $upTo; $index++) {
            if (! is_array($cursor)) {
                throw new RuntimeException(sprintf('Path %s does not exist in the fixture.', implode('.', $path)));
            }

            $cursor = &$cursor[$path[$index]];
        }

        return $cursor;
    }
}
