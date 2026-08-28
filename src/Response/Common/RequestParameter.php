<?php

declare(strict_types=1);

namespace DmmApiClient\Response\Common;

/**
 * リクエストパラメータが name/value のペア配列で返る場合の 1 要素。
 */
final class RequestParameter
{
    /**
     * @param string $name  パラメータ名（例: api_id）
     * @param string $value パラメータ値
     */
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
    }
}
