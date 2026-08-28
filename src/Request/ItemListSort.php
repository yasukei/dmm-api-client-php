<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

/**
 * 商品情報 API の並び順（`sort` パラメータ）。
 */
enum ItemListSort: string
{
    /** 人気順 */
    case Rank = 'rank';

    /** 価格が高い順 */
    case PriceHighToLow = 'price';

    /** 価格が安い順 */
    case PriceLowToHigh = '-price';

    /** 新着順 */
    case Date = 'date';

    /** 評価順 */
    case Review = 'review';

    /** マッチング順（`keyword` 指定時のみ有効） */
    case Match = 'match';
}
