<?php

declare(strict_types=1);

use DmmApiClient\Request\FloorListRequest;

test('エンドポイントを返す', function (): void {
    expect((new FloorListRequest())->endpoint())->toBe('/FloorList');
});

test('固有のクエリパラメータを持たない', function (): void {
    expect((new FloorListRequest())->toQueryParameters())->toBe([]);
});
