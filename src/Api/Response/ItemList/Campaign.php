<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 商品に適用されているキャンペーン 1 件。
 *
 * 日付は `DateTimeImmutable` に変換せず、API が返した文字列のまま保持する。
 * サービスによって書式が異なり、同じ項目として扱えないため。
 *
 * - 動画（digital）: `date_begin` `date_end` とも `2026-09-04 10:10:00` の書式
 * - 同人（doujin）: `date_begin` は `2026-09-04T00:00:00Z`、`date_end` は常に空文字
 */
final readonly class Campaign
{
    /**
     * @param string $dateBegin 開始日時
     * @param string $dateEnd   終了日時。同人のフロアでは空文字
     * @param string $title     キャンペーン名（例: 50%OFF）
     */
    public function __construct(
        #[MapFromKey('date_begin')]
        public string $dateBegin,
        #[MapFromKey('date_end')]
        public string $dateEnd,
        public string $title,
    ) {
    }
}
