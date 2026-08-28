<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Request\Request;
use DmmApiClient\Request\SeriesSearchRequest;
use DmmApiClient\Response\SeriesSearch\SeriesSearchResponse;

/**
 * シリーズ検索 API (`/SeriesSearch`) を呼び出す。
 */
final class SeriesSearchCommand extends FloorScopedSearchCommand
{
    public function name(): string
    {
        return 'series-search';
    }

    public function description(): string
    {
        return 'シリーズを検索する (/SeriesSearch)';
    }

    protected function subject(): string
    {
        return 'シリーズ';
    }

    protected function endpoint(): string
    {
        return SeriesSearchRequest::ENDPOINT;
    }

    protected function createFloorScopedRequest(
        string $floorId,
        ?string $initial,
        ?int $hits,
        ?int $offset,
    ): Request {
        return new SeriesSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function responseClass(): string
    {
        return SeriesSearchResponse::class;
    }
}
