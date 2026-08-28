<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

/**
 * 商品情報 API の絞り込み対象カテゴリ（`article` パラメータ）。
 *
 * `ItemListRequest::$articleId` と対で指定する。
 */
enum ArticleType: string
{
    /** 女優 */
    case Actress = 'actress';

    /** 作者 */
    case Author = 'author';

    /** ジャンル */
    case Genre = 'genre';

    /** シリーズ */
    case Series = 'series';

    /** メーカー */
    case Maker = 'maker';
}
