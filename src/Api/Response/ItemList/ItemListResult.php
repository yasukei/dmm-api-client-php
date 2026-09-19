<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapAsString;
use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 商品情報 API のレスポンスの `result` 部。
 */
final readonly class ItemListResult
{
    /**
     * @param string     $status        ステータスコード
     * @param int        $resultCount   このレスポンスに含まれる件数
     * @param int        $totalCount    検索結果の総件数
     * @param int        $firstPosition 検索開始位置（1 始まり）
     * @param list<Item> $items         検索結果の商品一覧
     */
    public function __construct(
        #[MapAsString]
        public string $status,
        #[MapFromKey('result_count')]
        public int $resultCount,
        #[MapFromKey('total_count')]
        public int $totalCount,
        #[MapFromKey('first_position')]
        public int $firstPosition,
        public array $items = [],
    ) {}
}
