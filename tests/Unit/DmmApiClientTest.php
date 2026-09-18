<?php

declare(strict_types=1);

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Exception\ApiErrorException;
use DmmApiClient\Api\Exception\MalformedResponseException;
use DmmApiClient\Api\Exception\ResponseValidationException;
use DmmApiClient\Api\Exception\TransportException;
use DmmApiClient\Api\Request\ActressSearchRequest;
use DmmApiClient\Api\Request\ArticleFilter;
use DmmApiClient\Api\Request\ArticleType;
use DmmApiClient\Api\Request\AuthorSearchRequest;
use DmmApiClient\Api\Request\FloorListRequest;
use DmmApiClient\Api\Request\GenreSearchRequest;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\ItemListSort;
use DmmApiClient\Api\Request\MakerSearchRequest;
use DmmApiClient\Api\Request\SeriesSearchRequest;
use DmmApiClient\Api\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Api\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Api\Response\FloorList\FloorListResponse;
use DmmApiClient\Api\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\Api\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Api\Response\SeriesSearch\SeriesSearchResponse;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\NetworkFailure;
use Tests\Support\StubHttpClient;

test('認証情報・リクエストパラメータ・output を載せた URI を組み立てる', function (): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, '{}'));

    $uri = $client->buildUri(new ItemListRequest(
        site: 'FANZA',
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
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, '{}'));

    $uri = $client->buildUri(new ItemListRequest(
        site: 'FANZA',
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
        'https://example.test/api',
        StubHttpClient::respondingWith(200, '{}'),
    );

    expect($client->buildUri(new FloorListRequest()))->toStartWith('https://example.test/api/FloorList?');
});

test('Accept ヘッダ付きの GET を送る', function (): void {
    $http = StubHttpClient::respondingWithFixture('item-list');

    (new DmmApiClient(credentials(), httpClient: $http))->itemList(new ItemListRequest(site: 'FANZA'));

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
    $response = $call(new DmmApiClient(credentials(), httpClient: $http));

    expect($response)->toBeObject()
        ->and($http->lastRequest()->getUri()->getPath())->toBe('/affiliate/v3' . $endpoint);
})->with([
    'itemList' => ['item-list', '/ItemList',
        fn (DmmApiClient $client): ItemListResponse => $client->itemList(new ItemListRequest(site: 'FANZA'))],
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

    expect((new DmmApiClient(credentials(), httpClient: $http))->floorList())
        ->toBeInstanceOf(FloorListResponse::class);
});

test('取得したレスポンスを DTO に変換する', function (): void {
    $http = StubHttpClient::respondingWithFixture('item-list');

    $response = (new DmmApiClient(credentials(), httpClient: $http))
        ->itemList(new ItemListRequest(site: 'FANZA'));

    expect($response->result->totalCount)->toBe(12450)
        ->and($response->result->items[0]->title)->toBe('サンプル動画作品');
});

test('API がエラーを返したら ApiErrorException にする', function (): void {
    $http = StubHttpClient::respondingWithFixture('error', 400);
    $client = new DmmApiClient(credentials(), httpClient: $http);

    try {
        $client->itemList(new ItemListRequest(site: 'FANZA'));
        Assert::fail('ApiErrorException が送出されませんでした。');
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
    $client = new DmmApiClient(credentials(), httpClient: $http);

    try {
        $client->itemList(new ItemListRequest(site: 'FANZA'));
        Assert::fail('ApiErrorException が送出されませんでした。');
    } catch (ApiErrorException $exception) {
        expect($exception->httpStatusCode)->toBe(503)
            ->and($exception->error)->toBeNull()
            ->and($exception->responseBody)->toBe('<html>Service Unavailable</html>');
    }
});

test('ボディが JSON でなければ MalformedResponseException にする', function (): void {
    $http = StubHttpClient::respondingWith(200, 'not json at all');
    $client = new DmmApiClient(credentials(), httpClient: $http);

    try {
        $client->itemList(new ItemListRequest(site: 'FANZA'));
        Assert::fail('MalformedResponseException が送出されませんでした。');
    } catch (MalformedResponseException $exception) {
        expect($exception->endpoint)->toBe('/ItemList')
            ->and($exception->responseBody)->toBe('not json at all');
    }
});

test('ボディが JSON オブジェクトでなければ MalformedResponseException にする', function (): void {
    $http = StubHttpClient::respondingWith(200, '"just a string"');

    expect(fn (): ItemListResponse => (new DmmApiClient(credentials(), httpClient: $http))
        ->itemList(new ItemListRequest(site: 'FANZA')))
        ->toThrow(MalformedResponseException::class, 'not a JSON object');
});

test('構造が仕様と合わなければ ResponseValidationException にする', function (): void {
    $http = StubHttpClient::respondingWith(200, (string) json_encode([
        'result' => ['status' => 200, 'result_count' => 'many', 'total_count' => 1, 'first_position' => 1],
    ]));

    expect(fn (): ItemListResponse => (new DmmApiClient(credentials(), httpClient: $http))
        ->itemList(new ItemListRequest(site: 'FANZA')))
        ->toThrow(ResponseValidationException::class);
});

test('通信に失敗したら TransportException にする', function (): void {
    $http = StubHttpClient::failingWith('Could not resolve host');
    $client = new DmmApiClient(credentials(), httpClient: $http);

    try {
        $client->itemList(new ItemListRequest(site: 'FANZA'));
        Assert::fail('TransportException が送出されませんでした。');
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
    $client = new DmmApiClient(credentials(), httpClient: $http);

    try {
        $client->itemList(new ItemListRequest(site: 'FANZA'));
        Assert::fail('TransportException が送出されませんでした。');
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

test('リクエストファクトリがストリームファクトリを兼ねていれば、自動検出しない', function (): void {
    // Psr17Factory はリクエストとストリームのファクトリを兼ねる。
    $response = withoutDiscovery(fn (): FloorListResponse => (new DmmApiClient(
        credentials(),
        httpClient: StubHttpClient::respondingWithFixture('floor-list'),
        requestFactory: new Psr17Factory(),
    ))->floorList());

    expect($response)->toBeInstanceOf(FloorListResponse::class);
});

test('ストリームファクトリを渡せば、リクエストファクトリが兼ねていなくても自動検出しない', function (): void {
    $requestFactory = new class () implements RequestFactoryInterface {
        public function createRequest(string $method, $uri): RequestInterface
        {
            return (new Psr17Factory())->createRequest($method, $uri);
        }
    };

    $response = withoutDiscovery(fn (): FloorListResponse => (new DmmApiClient(
        credentials(),
        httpClient: StubHttpClient::respondingWithFixture('floor-list'),
        requestFactory: $requestFactory,
        streamFactory: new Psr17Factory(),
    ))->floorList());

    expect($response)->toBeInstanceOf(FloorListResponse::class);
});

test('型付きメソッドを呼んだあとでも生ボディを参照できる', function (): void {
    $body = Tests\Support\Fixture::json('floor-list');
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, $body));

    $response = $client->floorList();

    expect($response)->toBeInstanceOf(FloorListResponse::class)
        ->and($client->lastResponseBody())->toBe($body);
});

test('DTO が知らないキーも生ボディには残る', function (): void {
    // 既定のマッパーは知らないキーを捨てるため、DTO 経由では辿れない。
    $body = '{"result":{"site":[{"name":"DMM.com","code":"DMM.com","service":[],"new_field":"x"}]}}';
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, $body));

    $client->floorList();

    expect($client->lastResponseBody())->toContain('new_field');
});

test('1 度も呼び出していなければ生ボディは null', function (): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, '{}'));

    expect($client->lastResponseBody())->toBeNull();
});

test('buildUri は送信しないので生ボディを更新しない', function (): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, '{"result":{}}'));

    $client->buildUri(new FloorListRequest());

    expect($client->lastResponseBody())->toBeNull();
});

test('生ボディは最後の呼び出しで上書きされる', function (): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(400, '{"result":{"status":400}}'));

    expect(fn (): mixed => $client->floorList())->toThrow(ApiErrorException::class);

    // エラーで終わった場合も、そのレスポンスの生ボディが残る。
    expect($client->lastResponseBody())->toBe('{"result":{"status":400}}');
});

test('通信に失敗したら、前の呼び出しの生ボディを返さない', function (): void {
    $inner = new class () implements ClientInterface {
        private int $calls = 0;

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            if (++$this->calls > 1) {
                throw new NetworkFailure('connection refused');
            }

            return new Response(400, ['Content-Type' => 'application/json'], '{"result":{"status":400}}');
        }
    };
    $client = new DmmApiClient(credentials(), httpClient: $inner);

    expect(fn (): mixed => $client->floorList())->toThrow(ApiErrorException::class);
    expect(fn (): mixed => $client->floorList())->toThrow(TransportException::class);

    expect($client->lastResponseBody())->toBeNull();
});
