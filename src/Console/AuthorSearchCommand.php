<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Request\AuthorSearchRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\AuthorSearch\AuthorSearchResponse;

/**
 * 作者検索 API (`/AuthorSearch`) を呼び出す。
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

    protected function endpoint(): string
    {
        return AuthorSearchRequest::ENDPOINT;
    }

    protected function createFloorScopedRequest(
        string $floorId,
        ?string $initial,
        ?int $hits,
        ?int $offset,
    ): Request {
        return new AuthorSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function responseClass(): string
    {
        return AuthorSearchResponse::class;
    }
}
