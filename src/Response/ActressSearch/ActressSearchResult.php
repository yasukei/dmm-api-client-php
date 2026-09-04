<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 女優検索 API のレスポンスの `result` 部。
 *
 * `status` と `first_position` は文字列で返る。商品情報 API が数値で返すのとは揃っていない。
 * この 2 つを文字列で返すのは、検索系の中でもこの API だけ。
 */
final readonly class ActressSearchResult
{
    /**
     * @param string        $status        ステータスコード
     * @param int           $resultCount   このレスポンスに含まれる件数
     * @param int           $totalCount    検索結果の総件数
     * @param string        $firstPosition 検索開始位置（1 始まり）
     * @param list<Actress> $actress       検索結果の女優一覧
     */
    public function __construct(
        public string $status,
        #[MapFromKey('result_count')]
        public int $resultCount,
        #[MapFromKey('total_count')]
        public int $totalCount,
        #[MapFromKey('first_position')]
        public string $firstPosition,
        public array $actress = [],
    ) {
    }
}
