<?php

declare(strict_types=1);

namespace DmmApiClient\Response\FloorList;

/**
 * フロア検索 API のレスポンスの `result` 部。
 *
 * 他の API と異なり、ステータスコードやページング情報を含まない。
 */
final readonly class FloorListResult
{
    /**
     * @param list<Site> $site サイトの一覧
     */
    public function __construct(
        public array $site = [],
    ) {
    }
}
