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
use DmmApiClient\Api\Request\RawRequest;
use DmmApiClient\Api\Request\Request;
use DmmApiClient\Api\Request\SeriesSearchRequest;
use DmmApiClient\Api\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Api\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Api\Response\FloorList\FloorListResponse;
use DmmApiClient\Api\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\Api\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Api\Response\SeriesSearch\SeriesSearchResponse;
use Http\Discovery\Exception\NotFoundException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\Fixture;
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
        fn(DmmApiClient $client): ItemListResponse => $client->itemList(new ItemListRequest(site: 'FANZA'))],
    'floorList' => ['floor-list', '/FloorList',
        fn(DmmApiClient $client): FloorListResponse => $client->floorList(new FloorListRequest())],
    'actressSearch' => ['actress-search', '/ActressSearch',
        fn(DmmApiClient $client): ActressSearchResponse => $client->actressSearch(new ActressSearchRequest())],
    'genreSearch' => ['genre-search', '/GenreSearch',
        fn(DmmApiClient $client): GenreSearchResponse => $client->genreSearch(new GenreSearchRequest('43'))],
    'makerSearch' => ['maker-search', '/MakerSearch',
        fn(DmmApiClient $client): MakerSearchResponse => $client->makerSearch(new MakerSearchRequest('43'))],
    'seriesSearch' => ['series-search', '/SeriesSearch',
        fn(DmmApiClient $client): SeriesSearchResponse => $client->seriesSearch(new SeriesSearchRequest('43'))],
    'authorSearch' => ['author-search', '/AuthorSearch',
        fn(DmmApiClient $client): AuthorSearchResponse => $client->authorSearch(new AuthorSearchRequest('80'))],
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

/*
 * 型付きメソッドの例外は、実装が共通かどうかに関わらずメソッドごとに確かめる。
 * 各行は [エンドポイント, 返す DTO のクラス, 呼び出し]。
 */
dataset('typed methods', [
    'itemList' => ['/ItemList', ItemListResponse::class,
        fn(DmmApiClient $client): ItemListResponse => $client->itemList(new ItemListRequest(site: 'FANZA'))],
    'floorList' => ['/FloorList', FloorListResponse::class,
        fn(DmmApiClient $client): FloorListResponse => $client->floorList(new FloorListRequest())],
    'actressSearch' => ['/ActressSearch', ActressSearchResponse::class,
        fn(DmmApiClient $client): ActressSearchResponse => $client->actressSearch(new ActressSearchRequest())],
    'genreSearch' => ['/GenreSearch', GenreSearchResponse::class,
        fn(DmmApiClient $client): GenreSearchResponse => $client->genreSearch(new GenreSearchRequest('43'))],
    'makerSearch' => ['/MakerSearch', MakerSearchResponse::class,
        fn(DmmApiClient $client): MakerSearchResponse => $client->makerSearch(new MakerSearchRequest('43'))],
    'seriesSearch' => ['/SeriesSearch', SeriesSearchResponse::class,
        fn(DmmApiClient $client): SeriesSearchResponse => $client->seriesSearch(new SeriesSearchRequest('43'))],
    'authorSearch' => ['/AuthorSearch', AuthorSearchResponse::class,
        fn(DmmApiClient $client): AuthorSearchResponse => $client->authorSearch(new AuthorSearchRequest('80'))],
]);

/*
 * fetchRaw に渡すリクエスト。各行は [リクエスト, エンドポイント]。
 */
dataset('raw requests', [
    'ItemListRequest' => [new ItemListRequest(site: 'FANZA'), '/ItemList'],
    'FloorListRequest' => [new FloorListRequest(), '/FloorList'],
    'ActressSearchRequest' => [new ActressSearchRequest(), '/ActressSearch'],
    'GenreSearchRequest' => [new GenreSearchRequest('43'), '/GenreSearch'],
    'MakerSearchRequest' => [new MakerSearchRequest('43'), '/MakerSearch'],
    'SeriesSearchRequest' => [new SeriesSearchRequest('43'), '/SeriesSearch'],
    'AuthorSearchRequest' => [new AuthorSearchRequest('80'), '/AuthorSearch'],
    'RawRequest' => [new RawRequest('/ItemList', ['site' => 'FANZA']), '/ItemList'],
]);

/*
 * API が 2xx 以外を返した場合のうち、エラーボディを ErrorResponse として読めないもの。
 * 各行は [ステータスコード, ボディ, Content-Type]。
 */
dataset('unreadable error bodies', [
    'JSON でない（300）' => [300, '<html>Multiple Choices</html>', 'text/html'],
    'JSON でない（404）' => [404, '<html>Not Found</html>', 'text/html'],
    'JSON でない（503）' => [503, '<html>Service Unavailable</html>', 'text/html'],
    'JSON だが ErrorResponse の構造でない' => [400, '{"result":{"status":400}}', 'application/json'],
    'JSON だがオブジェクトでない' => [500, '"Internal Server Error"', 'application/json'],
]);

/**
 * 通信エラーのメッセージ。Guzzle などと同じく、送信先 URI をそのまま載せる。
 */
function transportFailureMessage(string $endpoint): string
{
    return 'cURL error 6: Could not resolve host (see https://curl.se/libcurl/c/libcurl-errors.html)'
        . ' for https://api.dmm.com/affiliate/v3' . $endpoint
        . '?api_id=MY_API_ID&affiliate_id=myaffiliateid-999&output=json';
}

/**
 * $call が $class の例外を投げることを確かめ、その例外を返す。
 *
 * @template E of Throwable
 *
 * @param class-string<E>  $class
 * @param Closure(): mixed $call
 *
 * @return E
 */
function catchThrown(string $class, Closure $call): Throwable
{
    try {
        $call();
    } catch (Throwable $exception) {
        expect($exception)->toBeInstanceOf($class);

        /** @var E */
        return $exception;
    }

    Assert::fail(sprintf('%s が送出されませんでした。', $class));
}

scenario('API がエラーを返したら ApiErrorException にする', function (string $endpoint, string $responseClass, Closure $call): void {
    $body = Fixture::json('error');
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(400, $body));

    $exception = catchThrown(ApiErrorException::class, fn(): mixed => $call($client));

    expect($exception->httpStatusCode)->toBe(400)
        ->and($exception->getCode())->toBe(400)
        ->and($exception->error?->status)->toBe('400')
        ->and($exception->error?->message)->toBe('BAD REQUEST')
        ->and($exception->error?->errors)->toBe(['affiliate_id' => 'Invalid Request Error'])
        ->and($exception->responseBody)->toBe($body)
        ->and($exception->getMessage())
        ->toBe('DMM API returned 400 BAD REQUEST (affiliate_id: Invalid Request Error)');
})->with('typed methods');

scenario('エラーボディを解釈できなくても ApiErrorException にする', function (
    string $endpoint,
    string $responseClass,
    Closure $call,
    int $statusCode,
    string $body,
    string $contentType,
): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith($statusCode, $body, $contentType));

    $exception = catchThrown(ApiErrorException::class, fn(): mixed => $call($client));

    expect($exception->httpStatusCode)->toBe($statusCode)
        ->and($exception->error)->toBeNull()
        ->and($exception->responseBody)->toBe($body);
})->with('typed methods')->with('unreadable error bodies');

scenario('ボディが JSON でなければ MalformedResponseException にする', function (string $endpoint, string $responseClass, Closure $call): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, 'not json at all'));

    $exception = catchThrown(MalformedResponseException::class, fn(): mixed => $call($client));

    expect($exception->endpoint)->toBe($endpoint)
        ->and($exception->responseBody)->toBe('not json at all')
        ->and($exception->getPrevious())->toBeInstanceOf(JsonException::class);
})->with('typed methods');

scenario('ボディが JSON オブジェクトでなければ MalformedResponseException にする', function (
    string $endpoint,
    string $responseClass,
    Closure $call,
    string $body,
): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, $body));

    $exception = catchThrown(MalformedResponseException::class, fn(): mixed => $call($client));

    expect($exception->endpoint)->toBe($endpoint)
        ->and($exception->responseBody)->toBe($body)
        ->and($exception->getMessage())->toContain('not a JSON object');
})->with('typed methods')->with([
    '文字列' => ['"just a string"'],
    '数値' => ['42'],
    'null' => ['null'],
]);

scenario('構造が仕様と合わなければ ResponseValidationException にする', function (
    string $endpoint,
    string $responseClass,
    Closure $call,
    string $body,
): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, $body));

    $exception = catchThrown(ResponseValidationException::class, fn(): mixed => $call($client));

    expect($exception->targetClass)->toBe($responseClass)
        ->and($exception->errors)->not->toBeEmpty();
})->with('typed methods')->with([
    'result がない' => ['{}'],
    'result がオブジェクトでない' => ['{"result":"not an object"}'],
]);

scenario('通信に失敗したら TransportException にする', function (string $endpoint, string $responseClass, Closure $call): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::failingWith('Could not resolve host'));

    $exception = catchThrown(TransportException::class, fn(): mixed => $call($client));

    expect($exception->endpoint)->toBe($endpoint)
        ->and($exception->getMessage())->toContain('Could not resolve host')
        ->and($exception->getPrevious())->toBeInstanceOf(NetworkFailure::class);
})->with('typed methods');

scenario('通信エラーのメッセージから認証情報を伏せ字にする', function (string $endpoint, string $responseClass, Closure $call): void {
    // Guzzle などは PSR-18 の例外メッセージに送信先 URI をそのまま載せるため、
    // 何もしないと api_id と affiliate_id が例外のログに残ってしまう。
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::failingWith(transportFailureMessage($endpoint)));

    $exception = catchThrown(TransportException::class, fn(): mixed => $call($client));

    expect($exception->getMessage())->toContain('Could not resolve host')
        ->and($exception->getMessage())->toContain('api_id=***&affiliate_id=***')
        ->and($exception->getMessage())->not->toContain('MY_API_ID')
        ->and($exception->getMessage())->not->toContain('myaffiliateid-999');
})->with('typed methods');

scenario('fetchRaw はボディを変換せずにそのまま返す', function (Request $request, string $endpoint, string $body): void {
    $http = StubHttpClient::respondingWith(200, $body);

    $raw = (new DmmApiClient(credentials(), httpClient: $http))->fetchRaw($request);

    expect($raw)->toBe($body)
        ->and($http->requests)->toHaveCount(1)
        ->and($http->lastRequest()->getMethod())->toBe('GET')
        ->and($http->lastRequest()->getHeaderLine('Accept'))->toBe('application/json')
        ->and($http->lastRequest()->getUri()->getPath())->toBe('/affiliate/v3' . $endpoint);
})->with('raw requests')->with([
    // DTO への変換も JSON としての検証もしないので、どちらに失敗するボディでも例外にしない。
    '仕様どおりの JSON' => [Fixture::json('item-list')],
    '構造が仕様と合わない JSON' => ['{"result":"not an object"}'],
    'JSON でない' => ['not json at all'],
    '空' => [''],
]);

scenario('fetchRaw は 2xx を成功として扱う', function (Request $request, string $endpoint, int $statusCode): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith($statusCode, '{}'));

    expect($client->fetchRaw($request))->toBe('{}');
})->with('raw requests')->with([
    '200' => [200],
    '204' => [204],
    '299' => [299],
]);

scenario('fetchRaw は API がエラーを返したら ApiErrorException にする', function (Request $request, string $endpoint): void {
    $body = Fixture::json('error');
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(400, $body));

    $exception = catchThrown(ApiErrorException::class, fn(): string => $client->fetchRaw($request));

    expect($exception->httpStatusCode)->toBe(400)
        ->and($exception->error?->message)->toBe('BAD REQUEST')
        ->and($exception->error?->errors)->toBe(['affiliate_id' => 'Invalid Request Error'])
        ->and($exception->responseBody)->toBe($body)
        ->and($exception->getMessage())
        ->toBe('DMM API returned 400 BAD REQUEST (affiliate_id: Invalid Request Error)');
})->with('raw requests');

scenario('fetchRaw はエラーボディを解釈できなくても ApiErrorException にする', function (
    Request $request,
    string $endpoint,
    int $statusCode,
    string $body,
    string $contentType,
): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith($statusCode, $body, $contentType));

    $exception = catchThrown(ApiErrorException::class, fn(): string => $client->fetchRaw($request));

    expect($exception->httpStatusCode)->toBe($statusCode)
        ->and($exception->error)->toBeNull()
        ->and($exception->responseBody)->toBe($body);
})->with('raw requests')->with('unreadable error bodies');

scenario('fetchRaw は通信に失敗したら TransportException にする', function (Request $request, string $endpoint): void {
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::failingWith(transportFailureMessage($endpoint)));

    $exception = catchThrown(TransportException::class, fn(): string => $client->fetchRaw($request));

    expect($exception->endpoint)->toBe($endpoint)
        ->and($exception->getPrevious())->toBeInstanceOf(NetworkFailure::class)
        ->and($exception->getMessage())->toContain('Could not resolve host')
        ->and($exception->getMessage())->toContain('api_id=***&affiliate_id=***')
        ->and($exception->getMessage())->not->toContain('MY_API_ID')
        ->and($exception->getMessage())->not->toContain('myaffiliateid-999');
})->with('raw requests');

scenario('自動検出に任せた実装が見つからなければ NotFoundException にする', function (Closure $construct, string $message): void {
    $exception = catchThrown(NotFoundException::class, fn(): mixed => withoutDiscovery($construct));

    expect($exception->getMessage())->toContain($message);
})->with([
    'PSR-18 クライアント' => [
        fn(): DmmApiClient => new DmmApiClient(
            credentials(),
            requestFactory: new Psr17Factory(),
        ),
        'No PSR-18 clients found',
    ],
    'PSR-17 リクエストファクトリ' => [
        fn(): DmmApiClient => new DmmApiClient(
            credentials(),
            httpClient: StubHttpClient::respondingWith(200, '{}'),
        ),
        'No PSR-17 request factory found',
    ],
]);

test('PSR-18 の実装を渡さなくても自動検出する', function (): void {
    expect((new DmmApiClient(credentials()))->buildUri(new FloorListRequest()))
        ->toStartWith('https://api.dmm.com/affiliate/v3/FloorList?');
});

test('依存をすべて渡せば、自動検出しない', function (): void {
    $response = withoutDiscovery(fn(): FloorListResponse => (new DmmApiClient(
        credentials(),
        httpClient: StubHttpClient::respondingWithFixture('floor-list'),
        requestFactory: new Psr17Factory(),
    ))->floorList());

    expect($response)->toBeInstanceOf(FloorListResponse::class);
});

test('型付きメソッドが返す DTO は生ボディを持つ', function (): void {
    $body = Fixture::json('floor-list');
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, $body));

    $response = $client->floorList();

    expect($response->body())->toBe($body)
        ->and($response->json())->toBe(json_decode($body, true));
});

test('DTO が知らないキーも生ボディには残る', function (): void {
    // 既定のマッパーは知らないキーを捨てるため、DTO のプロパティからは辿れない。
    $body = '{"result":{"site":[{"name":"DMM.com","code":"DMM.com","service":[],"new_field":"x"}]}}';
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, $body));

    $response = $client->floorList();

    expect($response->body())->toBe($body)
        ->and($response->json())->toBe(json_decode($body, true));
});

test('生ボディは呼び出しごとの DTO に閉じていて、後の呼び出しで上書きされない', function (): void {
    $inner = new class implements ClientInterface {
        private int $calls = 0;

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $body = sprintf('{"result":{"site":[{"name":"call-%d","code":"DMM.com","service":[]}]}}', ++$this->calls);

            return new Response(200, ['Content-Type' => 'application/json'], $body);
        }
    };
    $client = new DmmApiClient(credentials(), httpClient: $inner);

    $first = $client->floorList();
    $second = $client->floorList();

    expect($first->body())->toContain('call-1')
        ->and($second->body())->toContain('call-2');
});

test('検証に失敗したら、例外が生ボディを持つ', function (): void {
    $body = '{"result":{"site":"not-a-list"}}';
    $client = new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, $body));

    $exception = catchThrown(ResponseValidationException::class, fn(): mixed => $client->floorList());

    expect($exception->responseBody)->toBe($body)
        ->and($exception->targetClass)->toBe(FloorListResponse::class)
        ->and($exception->getPrevious())->not->toBeNull();
});
