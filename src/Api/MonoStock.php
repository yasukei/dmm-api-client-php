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
     * ドキュメント上は存在するが、未使用と思われる。
     * リクエストパラメータとして指定しても、レスポンスは mono にフィルタされず、
     * 実レスポンスを断片的に見る限り、データとしても現れない。
     */
    case Mono = 'mono';
}
