<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Request\GenreSearchRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\GenreSearch\GenreSearchResponse;

/**
 * ジャンル検索 API (`/GenreSearch`) を呼び出す。
 */
final class GenreSearchCommand extends FloorScopedSearchCommand
{
    public function name(): string
    {
        return 'genre-search';
    }

    public function description(): string
    {
        return 'ジャンルを検索する (/GenreSearch)';
    }

    protected function subject(): string
    {
        return 'ジャンル';
    }

    protected function endpoint(): string
    {
        return GenreSearchRequest::ENDPOINT;
    }

    protected function createFloorScopedRequest(
        string $floorId,
        ?string $initial,
        ?int $hits,
        ?int $offset,
    ): Request {
        return new GenreSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function responseClass(): string
    {
        return GenreSearchResponse::class;
    }
}
