<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Request\MakerSearchRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\MakerSearch\MakerSearchResponse;

/**
 * メーカー検索 API (`/MakerSearch`) を呼び出す。
 */
final class MakerSearchCommand extends FloorScopedSearchCommand
{
    public function name(): string
    {
        return 'maker-search';
    }

    public function description(): string
    {
        return 'メーカーを検索する (/MakerSearch)';
    }

    protected function subject(): string
    {
        return 'メーカー';
    }

    protected function endpoint(): string
    {
        return MakerSearchRequest::ENDPOINT;
    }

    protected function createFloorScopedRequest(
        string $floorId,
        ?string $initial,
        ?int $hits,
        ?int $offset,
    ): Request {
        return new MakerSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function responseClass(): string
    {
        return MakerSearchResponse::class;
    }
}
