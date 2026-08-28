<?php

declare(strict_types=1);

use DmmApiClient\Exception\InvalidArgumentException;
use DmmApiClient\Request\AuthorSearchRequest;
use DmmApiClient\Request\GenreSearchRequest;
use DmmApiClient\Request\MakerSearchRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Request\SeriesSearchRequest;

/*
 * floor_id を必須とする 4 つの検索 API は、同じパラメータ構成とバリデーションを持つ。
 */

/**
 * 4 つのリクエストを同じ引数で組み立てたデータセット。
 *
 * @return array<string, array{Request}>
 */
function floorScopedRequests(string $floorId = '43', ?string $initial = null, ?int $hits = null, ?int $offset = null): array
{
    return [
        'GenreSearch' => [new GenreSearchRequest($floorId, $initial, $hits, $offset)],
        'MakerSearch' => [new MakerSearchRequest($floorId, $initial, $hits, $offset)],
        'SeriesSearch' => [new SeriesSearchRequest($floorId, $initial, $hits, $offset)],
        'AuthorSearch' => [new AuthorSearchRequest($floorId, $initial, $hits, $offset)],
    ];
}

/**
 * 組み立てを遅延させたデータセット。コンストラクタで例外になるケースに使う。
 *
 * @return array<string, array{Closure(): Request}>
 */
function floorScopedRequestFactories(string $floorId = '43', ?int $hits = null, ?int $offset = null): array
{
    return [
        'GenreSearch' => [fn (): Request => new GenreSearchRequest($floorId, hits: $hits, offset: $offset)],
        'MakerSearch' => [fn (): Request => new MakerSearchRequest($floorId, hits: $hits, offset: $offset)],
        'SeriesSearch' => [fn (): Request => new SeriesSearchRequest($floorId, hits: $hits, offset: $offset)],
        'AuthorSearch' => [fn (): Request => new AuthorSearchRequest($floorId, hits: $hits, offset: $offset)],
    ];
}

scenario('エンドポイントを返す', function (Request $request, string $endpoint): void {
    expect($request->endpoint())->toBe($endpoint);
})->with([
    'GenreSearch' => [new GenreSearchRequest('43'), '/GenreSearch'],
    'MakerSearch' => [new MakerSearchRequest('43'), '/MakerSearch'],
    'SeriesSearch' => [new SeriesSearchRequest('43'), '/SeriesSearch'],
    'AuthorSearch' => [new AuthorSearchRequest('43'), '/AuthorSearch'],
]);

scenario('floor_id だけ指定した場合は floor_id だけがクエリに載る', function (Request $request): void {
    expect($request->toQueryParameters())->toBe(['floor_id' => '43']);
})->with(floorScopedRequests());

scenario('指定した項目をすべてクエリに載せる', function (Request $request): void {
    expect($request->toQueryParameters())->toBe([
        'floor_id' => '43',
        'initial' => 'あ',
        'hits' => '500',
        'offset' => '101',
    ]);
})->with(floorScopedRequests(initial: 'あ', hits: 500, offset: 101));

scenario('hits の上限 500 を受け付ける', function (Request $request): void {
    expect($request->toQueryParameters())->toHaveKey('hits', '500');
})->with(floorScopedRequests(hits: 500));

scenario('offset に上限はない', function (Request $request): void {
    expect($request->toQueryParameters())->toHaveKey('offset', '1000000');
})->with(floorScopedRequests(offset: 1_000_000));

scenario('floor_id が空なら拒否する', function (Closure $make): void {
    expect($make)->toThrow(InvalidArgumentException::class, 'floor_id must not be empty.');
})->with(floorScopedRequestFactories(floorId: ''));

scenario('501 以上の hits を拒否する', function (Closure $make): void {
    expect($make)->toThrow(InvalidArgumentException::class, 'hits must be between 1 and 500');
})->with(floorScopedRequestFactories(hits: 501));

scenario('1 未満の offset を拒否する', function (Closure $make): void {
    expect($make)->toThrow(InvalidArgumentException::class, 'offset must be 1 or greater');
})->with(floorScopedRequestFactories(offset: 0));
