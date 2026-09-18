<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Request\FloorListRequest;
use DmmApiClient\Api\Response\FloorList\FloorListResponse;

/**
 * フロア検索 API (`/FloorList`) を呼び出す。
 */
final class FloorListCommand extends ApiCommand
{
    public function name(): string
    {
        return 'floor-list';
    }

    public function description(): string
    {
        return 'サイト・サービス・フロアの構成を取得する (/FloorList)';
    }

    protected function requestOptions(): array
    {
        return [];
    }

    protected function createRequest(Input $input): FloorListRequest
    {
        return new FloorListRequest();
    }

    protected function invoke(DmmApiClient $client, Input $input): FloorListResponse
    {
        return $client->floorList($this->createRequest($input));
    }
}
