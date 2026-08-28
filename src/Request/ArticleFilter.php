<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

use DmmApiClient\Exception\InvalidArgumentException;

/**
 * 商品情報 API の、カテゴリによる絞り込み条件 1 件。
 *
 * `article` と `article_id` の対に対応する。
 */
final readonly class ArticleFilter
{
    /**
     * @param ArticleType $type 絞り込み対象のカテゴリ
     * @param string      $id   $type に対応する ID（例: ジャンルなら "6533"）
     *
     * @throws InvalidArgumentException $id が空の場合
     */
    public function __construct(
        public ArticleType $type,
        public string $id,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('article_id must not be empty.');
        }
    }
}
