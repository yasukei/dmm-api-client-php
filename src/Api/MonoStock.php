<?php

declare(strict_types=1);

namespace DmmApiClient\Api;

/**
 * 通販（mono）商品の在庫状況
 *
 * リクエストは `mono_stock` パラメータに、レスポンスは `stock` フィールドに現れる。
 */
enum MonoStock: string
{
    /** 在庫あり */
    case Stock = 'stock';

    /** 予約商品（在庫あり） */
    case Reserve = 'reserve';

    /** 予約商品（キャンセル待ち） */
    case ReserveEmpty = 'reserve_empty';

    /** 在庫なし
     *
     * リクエストの絞り込み条件には使えない（指定しても、 empty 以外のデータも含めてデータが返ってくる）。
     * レスポンスのみに現れる。
     */
    case Empty = 'empty';

    /** DMM通販のみ
     *
     * 指定すると件数は減るが、返る商品に mono は現れない。何をフィルタしているのかは分からない。
     * レスポンスの stock としても、実データで一度も観測できていない。
     */
    case Mono = 'mono';

    /** オーダー
     *
     * 上記 5 つと違い、ドキュメントに記載が無く、実データから見つけた値。
     *
     * live-probe 時に DVD フロアで一例発見した。
     * リクエストの絞り込み条件としては使えない。指定しても読み捨てられる。
     */
    case Order = 'order';
}
