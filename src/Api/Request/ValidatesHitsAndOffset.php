<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

use DmmApiClient\Api\Exception\InvalidArgumentException;

/**
 * 取得件数 (hits) と検索開始位置 (offset) の範囲チェック。
 *
 * 範囲の値は API ごとに違うので各リクエストの定数に残し、ここでは検証と文言だけを揃える。
 *
 * @internal
 */
trait ValidatesHitsAndOffset
{
    /**
     * @throws InvalidArgumentException $hits が範囲外の場合
     */
    private static function assertHitsInRange(?int $hits, int $min, int $max): void
    {
        if ($hits !== null && ($hits < $min || $hits > $max)) {
            throw new InvalidArgumentException(
                sprintf('hits must be between %d and %d, %d given.', $min, $max, $hits),
            );
        }
    }

    /**
     * @param int|null $max 上限。API 側に上限が無い場合は null
     *
     * @throws InvalidArgumentException $offset が範囲外の場合
     */
    private static function assertOffsetInRange(?int $offset, int $min, ?int $max = null): void
    {
        if ($offset === null || ($offset >= $min && ($max === null || $offset <= $max))) {
            return;
        }

        $message = $max === null
            ? sprintf('offset must be %d or greater, %d given.', $min, $offset)
            : sprintf('offset must be between %d and %d, %d given.', $min, $max, $offset);

        throw new InvalidArgumentException($message);
    }
}
