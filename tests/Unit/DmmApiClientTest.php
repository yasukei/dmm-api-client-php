<?php

declare(strict_types=1);

use DmmApiClient\DmmApiClient;
use DmmApiClient\Exception\ApiErrorException;
use DmmApiClient\Exception\MalformedResponseException;
use DmmApiClient\Exception\ResponseValidationException;
use DmmApiClient\Exception\TransportException;
use DmmApiClient\Request\ActressSearchRequest;
use DmmApiClient\Request\ArticleFilter;
use DmmApiClient\Request\ArticleType;
use DmmApiClient\Request\AuthorSearchRequest;
use DmmApiClient\Request\FloorListRequest;
use DmmApiClient\Request\GenreSearchRequest;
use DmmApiClient\Request\ItemListRequest;
use DmmApiClient\Request\ItemListSort;
use DmmApiClient\Request\MakerSearchRequest;
use DmmApiClient\Request\SeriesSearchRequest;
use DmmApiClient\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Response\FloorList\FloorListResponse;
use DmmApiClient\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Response\ItemList\ItemListResponse;
use DmmApiClient\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Response\SeriesSearch\SeriesSearchResponse;
use DmmApiClient\SiteCode;
use Tests\Support\StubHttpClient;

test('認証情報・リクエストパラメータ・output を載せた URI を組み立てる', function (): void {
    $client = new DmmApiClient(credentials(), StubHttpClient::respondingWith(200, '{}'));

    $uri = $client->buildUri(new ItemListRequest(
        site: SiteCode::Fanza,
        floor: 'videoa',
        sort: ItemListSort::Date,
        hits: 20,
    ));

    expect(urldecode($uri))->toBe(
        'https://api.dmm.com/affiliate/v3/ItemList'
        . '?api_id=MY_API_ID&affiliate_id=myaffiliateid-999&site=FANZA&floor=videoa&sort=date&hits=20&output=json',
    );
});

test('複数 article をインデックス付きのクエリに展開する', function (): void {
    $client = new DmmApiClient(credentials(), StubHttpClient::respondingWith(200, '{}'));

    $uri = $client->buildUri(new ItemListRequest(
        site: SiteCode::Fanza,
        articles: [
            new ArticleFilter(ArticleType::Genre, '6533'),
            new ArticleFilter(ArticleType::Actress, '1078970'),
        ],
    ));

    expect(urldecode($uri))
        ->toContain('article[0]=genre&article[1]=actress')
        ->toContain('article_id[0]=6533&article_id[1]=1078970');
});

test('ベース URI を差し替えられる', function (): void {
    $client = new DmmApiClient(
        credentials(),
        StubHttpClient::respondingWith(200, '{}'),
        baseUri: 'https://example.test/api',
    );

    expect($client->buildUri(new FloorListRequest()))->toStartWith('https://example.test/api/FloorList?');
});

test('Accept ヘッダ付きの GET を送る', function (): void {
    $http = StubHttpClient::respondingWithFixture('item-list');

    (new DmmApiClient(credentials(), $http))->itemList(new ItemListRequest(site: SiteCode::Fanza));

    expect($http->requests)->toHaveCount(1)
        ->and($http->lastRequest()->getMethod())->toBe('GET')
        ->and($http->lastRequest()->getHeaderLine('Accept'))->toBe('application/json')
        ->and($http->lastQueryParameters())->toMatchArray([
            'api_id' => 'MY_API_ID',
            'affiliate_id' => 'myaffiliateid-999',
            'site' => 'FANZA',
            'output' => 'json',
        ]);
});

scenario('各エンドポイントが対応する DTO を返す', function (string $fixture, string $endpoint, Closure $call): void {
    $http = StubHttpClient::respondingWithFixture($fixture);

    // データセットのクロージャは戻り値型で DTO を縛っているため、
    // 別の型が返れば TypeError になる。
    $response = $call(new DmmApiClient(credentials(), $http));

    expect($response)->toBeObject()
        ->and($http->lastRequest()->getUri()->getPath())->toBe('/affiliate/v3' . $endpoint);
})->with([
    'itemList' => ['item-list', '/ItemList',
        fn (DmmApiClient $client): ItemListResponse => $client->itemList(new ItemListRequest(site: SiteCode::Fanza))],
    'floorList' => ['floor-list', '/FloorList',
        fn (DmmApiClient $client): FloorListResponse => $client->floorList(new FloorListRequest())],
    'actressSearch' => ['actress-search', '/ActressSearch',
        fn (DmmApiClient $client): ActressSearchResponse => $client->actressSearch(new ActressSearchRequest())],
    'genreSearch' => ['genre-search', '/GenreSearch',
        fn (DmmApiClient $client): GenreSearchResponse => $client->genreSearch(new GenreSearchRequest('43'))],
    'makerSearch' => ['maker-search', '/MakerSearch',
        fn (DmmApiClient $client): MakerSearchResponse => $client->makerSearch(new MakerSearchRequest('43'))],
    'seriesSearch' => ['series-search', '/SeriesSearch',
        fn (DmmApiClient $client): SeriesSearchResponse => $client->seriesSearch(new SeriesSearchRequest('43'))],
    'authorSearch' => ['author-search', '/AuthorSearch',
        fn (DmmApiClient $client): AuthorSearchResponse => $client->authorSearch(new AuthorSearchRequest('80'))],
]);

test('floorList は引数を省略できる', function (): void {
    $http = StubHttpClient::respondingWithFixture('floor-list');

    expect((new DmmApiClient(credentials(), $http))->floorList())
        ->toBeInstanceOf(FloorListResponse::class);
});

test('取得したレスポンスを DTO に変換する', function (): void {
    $http = StubHttpClient::respondingWithFixture('item-list');

    $response = (new DmmApiClient(credentials(), $http))
        ->itemList(new ItemListRequest(site: SiteCode::Fanza));

    expect($response->result->totalCount)->toBe(12450)
        ->and($response->result->items[0]->title)->toBe('サンプル動画作品');
});

test('API がエラーを返したら ApiErrorException にする', function (): void {
    $http = StubHttpClient::respondingWithFixture('error', 400);
    $client = new DmmApiClient(credentials(), $http);

    try {
        $client->itemList(new ItemListRequest(site: SiteCode::Fanza));
        $this->fail('ApiErrorException が送出されませんでした。');
    } catch (ApiErrorException $exception) {
        expect($exception->httpStatusCode)->toBe(400)
            ->and($exception->error?->status)->toBe(400)
            ->and($exception->error?->message)->toBe('BAD REQUEST')
            ->and($exception->error?->errors)->toBe(['affiliate_id' => 'Invalid Request Error'])
            ->and($exception->getMessage())
            ->toBe('DMM API returned 400 BAD REQUEST (affiliate_id: Invalid Request Error)');
    }
});

test('エラーボディを解釈できなくても ApiErrorException にする', function (): void {
    $http = StubHttpClient::respondingWith(503, '<html>Service Unavailable</html>', 'text/html');
    $client = new DmmApiClient(credentials(), $http);

    try {
        $client->itemList(new ItemListRequest(site: SiteCode::Fanza));
        $this->fail('ApiErrorException が送出されませんでした。');
    } catch (ApiErrorException $exception) {
        expect($exception->httpStatusCode)->toBe(503)
            ->and($exception->error)->toBeNull()
            ->and($exception->responseBody)->toBe('<html>Service Unavailable</html>');
    }
});

test('ボディが JSON でなければ MalformedResponseException にする', function (): void {
    $http = StubHttpClient::respondingWith(200, 'not json at all');
    $client = new DmmApiClient(credentials(), $http);

    try {
        $client->itemList(new ItemListRequest(site: SiteCode::Fanza));
        $this->fail('MalformedResponseException が送出されませんでした。');
    } catch (MalformedResponseException $exception) {
        expect($exception->endpoint)->toBe('/ItemList')
            ->and($exception->responseBody)->toBe('not json at all');
    }
});

test('ボディが JSON オブジェクトでなければ MalformedResponseException にする', function (): void {
    $http = StubHttpClient::respondingWith(200, '"just a string"');

    expect(fn (): ItemListResponse => (new DmmApiClient(credentials(), $http))
        ->itemList(new ItemListRequest(site: SiteCode::Fanza)))
        ->toThrow(MalformedResponseException::class, 'not a JSON object');
});

test('構造が仕様と合わなければ ResponseValidationException にする', function (): void {
    $http = StubHttpClient::respondingWith(200, (string) json_encode([
        'result' => ['status' => 200, 'result_count' => 'many', 'total_count' => 1, 'first_position' => 1],
    ]));

    expect(fn (): ItemListResponse => (new DmmApiClient(credentials(), $http))
        ->itemList(new ItemListRequest(site: SiteCode::Fanza)))
        ->toThrow(ResponseValidationException::class);
});

test('通信に失敗したら TransportException にする', function (): void {
    $http = StubHttpClient::failingWith('Could not resolve host');
    $client = new DmmApiClient(credentials(), $http);

    try {
        $client->itemList(new ItemListRequest(site: SiteCode::Fanza));
        $this->fail('TransportException が送出されませんでした。');
    } catch (TransportException $exception) {
        expect($exception->endpoint)->toBe('/ItemList')
            ->and($exception->getMessage())->toContain('Could not resolve host')
            ->and($exception->getPrevious())->toBeInstanceOf(Tests\Support\NetworkFailure::class);
    }
});

test('通信エラーのメッセージから認証情報を伏せ字にする', function (): void {
    // Guzzle などは PSR-18 の例外メッセージに送信先 URI をそのまま載せるため、
    // 何もしないと api_id と affiliate_id が例外のログに残ってしまう。
    $http = StubHttpClient::failingWith(
        'cURL error 6: Could not resolve host (see https://curl.se/libcurl/c/libcurl-errors.html)'
        . ' for https://api.dmm.com/affiliate/v3/ItemList'
        . '?api_id=MY_API_ID&affiliate_id=myaffiliateid-999&site=FANZA&output=json',
    );
    $client = new DmmApiClient(credentials(), $http);

    try {
        $client->itemList(new ItemListRequest(site: SiteCode::Fanza));
        $this->fail('TransportException が送出されませんでした。');
    } catch (TransportException $exception) {
        expect($exception->getMessage())->toContain('Could not resolve host')
            ->and($exception->getMessage())->toContain('api_id=***&affiliate_id=***')
            ->and($exception->getMessage())->not->toContain('MY_API_ID')
            ->and($exception->getMessage())->not->toContain('myaffiliateid-999');
    }
});

test('PSR-18 の実装を渡さなくても自動検出する', function (): void {
    expect((new DmmApiClient(credentials()))->buildUri(new FloorListRequest()))
        ->toStartWith('https://api.dmm.com/affiliate/v3/FloorList?');
});
