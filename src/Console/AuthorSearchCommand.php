<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Request\AuthorSearchRequest;

/**
 * 作者検索 API (`/AuthorSearch`) を呼び出す。
 *
 * @extends FloorScopedSearchCommand<AuthorSearchRequest>
 */
final class AuthorSearchCommand extends FloorScopedSearchCommand
{
    public function name(): string
    {
        return 'author-search';
    }

    public function description(): string
    {
        return '作者を検索する (/AuthorSearch)';
    }

    protected function subject(): string
    {
        return '作者';
    }

    protected function createFloorScopedRequest(
        string $floorId,
        ?string $initial,
        ?int $hits,
        ?int $offset,
    ): AuthorSearchRequest {
        return new AuthorSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function invoke(DmmApiClient $client, Input $input): object
    {
        return $client->authorSearch($this->createRequest($input));
    }
}
