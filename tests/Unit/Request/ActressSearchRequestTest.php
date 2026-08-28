<?php

declare(strict_types=1);

use DmmApiClient\Exception\InvalidArgumentException;
use DmmApiClient\Request\ActressSearchRequest;
use DmmApiClient\Request\ActressSearchSort;

test('エンドポイントを返す', function (): void {
    expect((new ActressSearchRequest())->endpoint())->toBe('/ActressSearch');
});

test('何も指定しなければクエリは空になる', function (): void {
    expect((new ActressSearchRequest())->toQueryParameters())->toBe([]);
});

test('指定した項目をすべてクエリに載せる', function (): void {
    $request = new ActressSearchRequest(
        initial: 'あ',
        actressId: '1078970',
        keyword: 'あさみ',
        gteBust: 85,
        lteBust: 95,
        gteWaist: 55,
        lteWaist: 65,
        gteHip: 80,
        lteHip: 95,
        gteHeight: 150,
        lteHeight: 175,
        gteBirthday: new DateTimeImmutable('1990-01-01'),
        lteBirthday: new DateTimeImmutable('1999-12-31'),
        sort: ActressSearchSort::BustDesc,
        hits: 100,
        offset: 21,
    );

    expect($request->toQueryParameters())->toBe([
        'initial' => 'あ',
        'actress_id' => '1078970',
        'keyword' => 'あさみ',
        'gte_bust' => '85',
        'lte_bust' => '95',
        'gte_waist' => '55',
        'lte_waist' => '65',
        'gte_hip' => '80',
        'lte_hip' => '95',
        'gte_height' => '150',
        'lte_height' => '175',
        'gte_birthday' => '1990-01-01',
        'lte_birthday' => '1999-12-31',
        'sort' => '-bust',
        'hits' => '100',
        'offset' => '21',
    ]);
});

test('生年月日は時刻を落として日付だけ載せる', function (): void {
    $request = new ActressSearchRequest(gteBirthday: new DateTimeImmutable('1990-01-01 13:45:59'));

    expect($request->toQueryParameters())->toBe(['gte_birthday' => '1990-01-01']);
});

scenario('範囲外の hits を拒否する', function (int $hits): void {
    expect(fn (): ActressSearchRequest => new ActressSearchRequest(hits: $hits))
        ->toThrow(InvalidArgumentException::class, 'hits must be between 1 and 100');
})->with([[0], [ActressSearchRequest::HITS_MAX + 1]]);

test('offset に上限はない', function (): void {
    expect((new ActressSearchRequest(offset: 1000000))->toQueryParameters())
        ->toBe(['offset' => '1000000']);
});

scenario('1 未満の offset は拒否する', function (int $offset): void {
    expect(fn (): ActressSearchRequest => new ActressSearchRequest(offset: $offset))
        ->toThrow(InvalidArgumentException::class, 'offset must be 1 or greater');
})->with([[0], [-1]]);
