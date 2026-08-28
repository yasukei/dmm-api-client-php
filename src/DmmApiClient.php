<?php

declare(strict_types=1);

namespace DmmApiClient;

use DmmApiClient\Exception\ApiErrorException;
use DmmApiClient\Exception\MalformedResponseException;
use DmmApiClient\Exception\ResponseValidationException;
use DmmApiClient\Exception\TransportException;
use DmmApiClient\Request\ActressSearchRequest;
use DmmApiClient\Request\AuthorSearchRequest;
use DmmApiClient\Request\Credentials;
use DmmApiClient\Request\FloorListRequest;
use DmmApiClient\Request\GenreSearchRequest;
use DmmApiClient\Request\ItemListRequest;
use DmmApiClient\Request\MakerSearchRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Request\SeriesSearchRequest;
use DmmApiClient\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Response\Error\ErrorResponse;
use DmmApiClient\Response\FloorList\FloorListResponse;
use DmmApiClient\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Response\ItemList\ItemListResponse;
use DmmApiClient\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Response\ResponseMapper;
use DmmApiClient\Response\SeriesSearch\SeriesSearchResponse;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * DMM ウェブサービス API v3 のクライアント。
 *
 * HTTP の送受信は PSR-18 のクライアントに委譲する。実装を渡さなかった場合は
 * インストール済みの PSR-18 / PSR-17 実装を自動検出する。
 */
final readonly class DmmApiClient
{
    /** API のベース URI。 */
    public const string DEFAULT_BASE_URI = 'https://api.dmm.com/affiliate/v3';

    private ClientInterface $httpClient;

    private RequestFactoryInterface $requestFactory;

    private ResponseMapper $responseMapper;

    /**
     * @param Credentials                  $credentials    API ID とアフィリエイト ID
     * @param ClientInterface|null         $httpClient     PSR-18 クライアント。null なら自動検出する
     * @param RequestFactoryInterface|null $requestFactory PSR-17 リクエストファクトリ。null なら自動検出する
     * @param ResponseMapper|null          $responseMapper レスポンスの検証・マッピング担当。null なら既定の設定で生成する
     * @param string                       $baseUri        API のベース URI。末尾のスラッシュは不要
     */
    public function __construct(
        private Credentials $credentials,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?ResponseMapper $responseMapper = null,
        private string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->responseMapper = $responseMapper ?? new ResponseMapper();
    }

    /**
     * 商品情報 API (`/ItemList`)。
     *
     * @throws TransportException           HTTP 通信に失敗した場合
     * @throws ApiErrorException            API がエラーを返した場合
     * @throws MalformedResponseException   レスポンスが JSON として読めなかった場合
     * @throws ResponseValidationException  レスポンスが期待する構造と一致しなかった場合
     */
    public function itemList(ItemListRequest $request): ItemListResponse
    {
        return $this->send($request, ItemListResponse::class);
    }

    /**
     * フロア検索 API (`/FloorList`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function floorList(FloorListRequest $request = new FloorListRequest()): FloorListResponse
    {
        return $this->send($request, FloorListResponse::class);
    }

    /**
     * 女優検索 API (`/ActressSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function actressSearch(ActressSearchRequest $request): ActressSearchResponse
    {
        return $this->send($request, ActressSearchResponse::class);
    }

    /**
     * ジャンル検索 API (`/GenreSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function genreSearch(GenreSearchRequest $request): GenreSearchResponse
    {
        return $this->send($request, GenreSearchResponse::class);
    }

    /**
     * メーカー検索 API (`/MakerSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function makerSearch(MakerSearchRequest $request): MakerSearchResponse
    {
        return $this->send($request, MakerSearchResponse::class);
    }

    /**
     * シリーズ検索 API (`/SeriesSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function seriesSearch(SeriesSearchRequest $request): SeriesSearchResponse
    {
        return $this->send($request, SeriesSearchResponse::class);
    }

    /**
     * 作者検索 API (`/AuthorSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function authorSearch(AuthorSearchRequest $request): AuthorSearchResponse
    {
        return $this->send($request, AuthorSearchResponse::class);
    }

    /**
     * 実際に送信される URI を組み立てる。デバッグや、送信前の確認に使う。
     */
    public function buildUri(Request $request): string
    {
        $parameters = $this->credentials->toQueryParameters()
            + $request->toQueryParameters()
            + ['output' => 'json'];

        return $this->baseUri . $request->endpoint() . '?' . http_build_query($parameters);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $responseClass
     *
     * @return T
     */
    private function send(Request $request, string $responseClass): object
    {
        $httpRequest = $this->requestFactory
            ->createRequest('GET', $this->buildUri($request))
            ->withHeader('Accept', 'application/json');

        try {
            $httpResponse = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            throw TransportException::fromClientException($request->endpoint(), $exception);
        }

        $body = (string) $httpResponse->getBody();
        $statusCode = $httpResponse->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw $this->createApiError($statusCode, $body);
        }

        return $this->responseMapper->map($responseClass, $this->decode($request->endpoint(), $body));
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $endpoint, string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw MalformedResponseException::fromJsonException($endpoint, $body, $exception);
        }

        if (! is_array($decoded)) {
            throw MalformedResponseException::notAnObject($endpoint, $body);
        }

        return $decoded;
    }

    /**
     * エラーボディを {@see ErrorResponse} として読み取り、読めなければ生のボディを添えて例外にする。
     */
    private function createApiError(int $statusCode, string $body): ApiErrorException
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $error = $this->responseMapper->map(ErrorResponse::class, $decoded);
        } catch (JsonException | ResponseValidationException) {
            return ApiErrorException::fromUnreadableBody($statusCode, $body);
        }

        return ApiErrorException::fromErrorResult($statusCode, $error->result, $body);
    }
}
