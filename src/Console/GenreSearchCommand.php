<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Request\GenreSearchRequest;

/**
 * ジャンル検索 API (`/GenreSearch`) を呼び出す。
 *
 * @extends FloorScopedSearchCommand<GenreSearchRequest>
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
    ): GenreSearchRequest {
        return new GenreSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function invoke(DmmApiClient $client, Input $input): object
    {
        return $client->genreSearch($this->createRequest($input));
    }
}
