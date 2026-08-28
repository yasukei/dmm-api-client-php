<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Request\FloorListRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\FloorList\FloorListResponse;

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

    protected function endpoint(): string
    {
        return FloorListRequest::ENDPOINT;
    }

    protected function createRequest(Input $input): Request
    {
        return new FloorListRequest();
    }

    protected function responseClass(): string
    {
        return FloorListResponse::class;
    }
}
