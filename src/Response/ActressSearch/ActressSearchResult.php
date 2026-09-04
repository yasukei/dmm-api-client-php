<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 女優検索 API のレスポンスの `result` 部。
 *
 * `status` と `first_position` は文字列で返る。`total_count` は 0 件のときだけ数値で、
 * それ以外は文字列で返る。商品情報 API はいずれも数値で返すので、揃っていない。
 */
final readonly class ActressSearchResult
{
    /**
     * @param string        $status        ステータスコード
     * @param int           $resultCount   このレスポンスに含まれる件数
     * @param int|string    $totalCount    検索結果の総件数（0 件のときだけ数値）
     * @param string        $firstPosition 検索開始位置（1 始まり）
     * @param list<Actress> $actress       検索結果の女優一覧
     */
    public function __construct(
        public string $status,
        #[MapFromKey('result_count')]
        public int $resultCount,
        #[MapFromKey('total_count')]
        public int|string $totalCount,
        #[MapFromKey('first_position')]
        public string $firstPosition,
        public array $actress = [],
    ) {
    }
}
