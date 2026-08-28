<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

/**
 * 通販（mono）商品の在庫絞り込み（`mono_stock` パラメータ）。
 *
 * 未指定（null）の場合は絞り込みを行わない。
 */
enum MonoStock: string
{
    /** 在庫あり */
    case Stock = 'stock';

    /** 予約商品（在庫あり） */
    case Reserve = 'reserve';

    /** 予約商品（キャンセル待ち） */
    case ReserveEmpty = 'reserve_empty';

    /** DMM通販のみ */
    case Mono = 'mono';
}
