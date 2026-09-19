<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ActressSearch;

use CuyZ\Valinor\Mapper\Configurator\MapAsInt;
use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 女優検索 API のレスポンスの `result` 部。
 *
 * `status` は文字列で返る。商品情報 API は数値で返すので、揃っていない。
 *
 * `first_position` は文字列で、`total_count` は 0 件のときだけ数値、それ以外は文字列で返る。
 * 他の検索 API と揃えるため、数値として読める文字列は int に変換する。
 */
final readonly class ActressSearchResult
{
    /**
     * @param string        $status        ステータスコード
     * @param int           $resultCount   このレスポンスに含まれる件数
     * @param int           $totalCount    検索結果の総件数
     * @param int           $firstPosition 検索開始位置（1 始まり）
     * @param list<Actress> $actress       検索結果の女優一覧
     */
    public function __construct(
        public string $status,
        #[MapFromKey('result_count')]
        public int $resultCount,
        #[MapFromKey('total_count')]
        #[MapAsInt]
        public int $totalCount,
        #[MapFromKey('first_position')]
        #[MapAsInt]
        public int $firstPosition,
        public array $actress = [],
    ) {}
}
