<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 女優検索 API のレスポンスの `result` 部。
 */
final readonly class ActressSearchResult
{
    /**
     * @param int           $status        ステータスコード
     * @param int           $resultCount   このレスポンスに含まれる件数
     * @param int           $totalCount    検索結果の総件数
     * @param int           $firstPosition 検索開始位置（1 始まり）
     * @param list<Actress> $actress       検索結果の女優一覧
     */
    public function __construct(
        public int $status,
        #[MapFromKey('result_count')]
        public int $resultCount,
        #[MapFromKey('total_count')]
        public int $totalCount,
        #[MapFromKey('first_position')]
        public int $firstPosition,
        public array $actress = [],
    ) {
    }
}
