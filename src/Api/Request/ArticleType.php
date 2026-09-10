<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

/**
 * 商品情報 API の絞り込み対象カテゴリ（`article` パラメータ）。
 *
 * ID と対にして {@see ArticleFilter} にまとめ、{@see ItemListRequest::$articles} へ渡す。
 *
 * Actress から Maker までは API ドキュメントに挙がっているもの。ドキュメントに無いものは
 * その後ろへ足していく。
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

    /**
     * レーベル
     *
     * ここから下の 5 つは、ドキュメントに記載が無い。`iteminfo` が返す分類を live-probe で
     * 順に指定してみたところ、絞り込みが効いたもの。いずれも、返ってきた商品が全件その ID を
     * 持っていることを確かめてある。
     *
     * ドキュメントに無いので、DMM 側の都合でいつ使えなくなるかは分からない。
     */
    case Label = 'label';

    /** 出版社 */
    case Manufacture = 'manufacture';

    /** 監督 */
    case Director = 'director';

    /** 男優 */
    case Actor = 'actor';

    /** アーティスト */
    case Artist = 'artist';
}
