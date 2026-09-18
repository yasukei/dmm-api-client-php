<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Request\SeriesSearchRequest;
use DmmApiClient\Api\Response\SeriesSearch\SeriesSearchResponse;

/**
 * シリーズ検索 API (`/SeriesSearch`) を呼び出す。
 *
 * @extends FloorScopedSearchCommand<SeriesSearchRequest>
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

    protected function createFloorScopedRequest(
        string $floorId,
        ?string $initial,
        ?int $hits,
        ?int $offset,
    ): SeriesSearchRequest {
        return new SeriesSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function invoke(DmmApiClient $client, Input $input): SeriesSearchResponse
    {
        return $client->seriesSearch($this->createRequest($input));
    }
}
