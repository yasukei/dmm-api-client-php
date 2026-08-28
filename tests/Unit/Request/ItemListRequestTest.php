<?php

declare(strict_types=1);

use DmmApiClient\Exception\InvalidArgumentException;
use DmmApiClient\Request\ArticleFilter;
use DmmApiClient\Request\ArticleType;
use DmmApiClient\Request\ItemListRequest;
use DmmApiClient\Request\ItemListSort;
use DmmApiClient\Request\MonoStock;
use DmmApiClient\SiteCode;

test('エンドポイントを返す', function (): void {
    expect((new ItemListRequest(site: SiteCode::Fanza))->endpoint())->toBe('/ItemList');
});

test('site だけ指定した場合は site だけがクエリに載る', function (): void {
    expect((new ItemListRequest(site: SiteCode::DmmCom))->toQueryParameters())
        ->toBe(['site' => 'DMM.com']);
});

test('指定した項目をすべてクエリに載せる', function (): void {
    $request = new ItemListRequest(
        site: SiteCode::Fanza,
        service: 'digital',
        floor: 'videoa',
        keyword: 'アクション',
        cid: 'mizd00320',
        gteDate: new DateTimeImmutable('2016-04-01 00:00:00'),
        lteDate: new DateTimeImmutable('2016-04-30 23:59:59'),
        monoStock: MonoStock::Stock,
        sort: ItemListSort::PriceLowToHigh,
        hits: 100,
        offset: 21,
    );

    expect($request->toQueryParameters())->toBe([
        'site' => 'FANZA',
        'service' => 'digital',
        'floor' => 'videoa',
        'keyword' => 'アクション',
        'cid' => 'mizd00320',
        'gte_date' => '2016-04-01T00:00:00',
        'lte_date' => '2016-04-30T23:59:59',
        'mono_stock' => 'stock',
        'sort' => '-price',
        'hits' => '100',
        'offset' => '21',
    ]);
});

test('article は 1 件でもインデックス付き配列で載せる', function (): void {
    $request = new ItemListRequest(
        site: SiteCode::Fanza,
        articles: [new ArticleFilter(ArticleType::Genre, '6533')],
    );

    expect($request->toQueryParameters())->toBe([
        'site' => 'FANZA',
        'article' => ['genre'],
        'article_id' => ['6533'],
    ]);
});

test('article を複数指定できる', function (): void {
    $request = new ItemListRequest(
        site: SiteCode::Fanza,
        articles: [
            new ArticleFilter(ArticleType::Genre, '6533'),
            new ArticleFilter(ArticleType::Actress, '1078970'),
        ],
    );

    expect($request->toQueryParameters())->toBe([
        'site' => 'FANZA',
        'article' => ['genre', 'actress'],
        'article_id' => ['6533', '1078970'],
    ]);
});

test('複数 article は http_build_query でインデックス付きの形に展開される', function (): void {
    $request = new ItemListRequest(
        site: SiteCode::Fanza,
        articles: [
            new ArticleFilter(ArticleType::Genre, '6533'),
            new ArticleFilter(ArticleType::Actress, '1078970'),
        ],
    );

    expect(urldecode(http_build_query($request->toQueryParameters())))
        ->toBe('site=FANZA&article[0]=genre&article[1]=actress&article_id[0]=6533&article_id[1]=1078970');
});

scenario('hits の境界値を受け付ける', function (int $hits): void {
    expect((new ItemListRequest(site: SiteCode::Fanza, hits: $hits))->hits)->toBe($hits);
})->with([[ItemListRequest::HITS_MIN], [50], [ItemListRequest::HITS_MAX]]);

scenario('範囲外の hits を拒否する', function (int $hits): void {
    expect(fn (): ItemListRequest => new ItemListRequest(site: SiteCode::Fanza, hits: $hits))
        ->toThrow(InvalidArgumentException::class, 'hits must be between 1 and 100');
})->with([[0], [-1], [ItemListRequest::HITS_MAX + 1]]);

scenario('offset の境界値を受け付ける', function (int $offset): void {
    expect((new ItemListRequest(site: SiteCode::Fanza, offset: $offset))->offset)->toBe($offset);
})->with([[ItemListRequest::OFFSET_MIN], [ItemListRequest::OFFSET_MAX]]);

scenario('範囲外の offset を拒否する', function (int $offset): void {
    expect(fn (): ItemListRequest => new ItemListRequest(site: SiteCode::Fanza, offset: $offset))
        ->toThrow(InvalidArgumentException::class, 'offset must be between 1 and 50000');
})->with([[0], [-1], [ItemListRequest::OFFSET_MAX + 1]]);
