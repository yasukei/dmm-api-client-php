<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Request\MakerSearchRequest;

/**
 * メーカー検索 API (`/MakerSearch`) を呼び出す。
 *
 * @extends FloorScopedSearchCommand<MakerSearchRequest>
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
    ): MakerSearchRequest {
        return new MakerSearchRequest($floorId, $initial, $hits, $offset);
    }

    protected function invoke(DmmApiClient $client, Input $input): object
    {
        return $client->makerSearch($this->createRequest($input));
    }
}
