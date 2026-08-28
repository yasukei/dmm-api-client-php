<?php

declare(strict_types=1);

use DmmApiClient\Request\ItemListRequest;
use DmmApiClient\Request\RawRequest;

test('エンドポイントとパラメータをそのまま返す', function (): void {
    $request = new RawRequest(ItemListRequest::ENDPOINT, ['site' => 'FANZA', 'hits' => '20']);

    expect($request->endpoint())->toBe('/ItemList')
        ->and($request->toQueryParameters())->toBe(['site' => 'FANZA', 'hits' => '20']);
});

test('パラメータを省略できる', function (): void {
    expect((new RawRequest('/FloorList'))->toQueryParameters())->toBe([]);
});

test('専用クラスなら弾かれる値でも、検証せずに通す', function (): void {
    // ItemListRequest なら hits=9999 は InvalidArgumentException になる。
    $request = new RawRequest(ItemListRequest::ENDPOINT, ['site' => 'FANZA', 'hits' => '9999']);

    expect($request->toQueryParameters())->toHaveKey('hits', '9999');
});

test('配列の値をそのまま保持する', function (): void {
    $request = new RawRequest(ItemListRequest::ENDPOINT, [
        'article' => ['genre', 'actress'],
        'article_id' => ['6533', '1078970'],
    ]);

    expect(urldecode(http_build_query($request->toQueryParameters())))
        ->toBe('article[0]=genre&article[1]=actress&article_id[0]=6533&article_id[1]=1078970');
});
