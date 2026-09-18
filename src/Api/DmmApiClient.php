<?php

declare(strict_types=1);

namespace DmmApiClient\Api;

use DmmApiClient\Api\Exception\ApiErrorException;
use DmmApiClient\Api\Exception\MalformedResponseException;
use DmmApiClient\Api\Exception\ResponseValidationException;
use DmmApiClient\Api\Exception\TransportException;
use DmmApiClient\Api\Request\ActressSearchRequest;
use DmmApiClient\Api\Request\AuthorSearchRequest;
use DmmApiClient\Api\Request\Credentials;
use DmmApiClient\Api\Request\FloorListRequest;
use DmmApiClient\Api\Request\GenreSearchRequest;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\MakerSearchRequest;
use DmmApiClient\Api\Request\Request;
use DmmApiClient\Api\Request\SeriesSearchRequest;
use DmmApiClient\Api\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Api\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Api\Response\Error\ErrorResponse;
use DmmApiClient\Api\Response\FloorList\FloorListResponse;
use DmmApiClient\Api\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\Api\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Api\Response\ResponseMapper;
use DmmApiClient\Api\Response\SeriesSearch\SeriesSearchResponse;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

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

    private CapturingHttpClient $httpClient;

    private RequestFactoryInterface $requestFactory;

    private ResponseMapper $responseMapper;

    /**
     * @param Credentials                  $credentials    API ID とアフィリエイト ID
     * @param string                       $baseUri        API のベース URI。末尾のスラッシュは不要
     * @param ClientInterface|null         $httpClient     PSR-18 クライアント。null なら自動検出する
     * @param RequestFactoryInterface|null $requestFactory PSR-17 リクエストファクトリ。null なら自動検出する
     * @param StreamFactoryInterface|null  $streamFactory  PSR-17 ストリームファクトリ。生ボディのキャプチャに使う。null ならリクエストファクトリが兼ねていればそれを使い、兼ねていなければ自動検出する
     * @param ResponseMapper|null          $responseMapper レスポンスの検証・マッピング担当。null なら既定の設定で生成する
     */
    public function __construct(
        private Credentials $credentials,
        private string $baseUri = self::DEFAULT_BASE_URI,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?ResponseMapper $responseMapper = null,
    ) {
        $httpClient ??= Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();

        // PSR-17 の実装はリクエストとストリームのファクトリを 1 クラスで兼ねることが多い。
        // 兼ねていればそれを使い、依存をすべて渡された場合には自動検出を走らせない。
        $streamFactory ??= $this->requestFactory instanceof StreamFactoryInterface
            ? $this->requestFactory
            : Psr17FactoryDiscovery::findStreamFactory();

        // 生ボディをキャプチャするため、必ず包んでから使う（{@see self::lastResponseBody()}）。
        $this->httpClient = new CapturingHttpClient($httpClient, $streamFactory);
        $this->responseMapper = $responseMapper ?? new ResponseMapper();
    }

    /**
     * 直前の呼び出しで受け取ったレスポンスの生ボディ。まだ呼び出していない場合と、
     * 直前の呼び出しが通信に失敗してレスポンスを受け取れなかった場合は null。
     *
     * 型付きメソッドは DTO を返すため、DTO が知らないキーは落ちる。DMM が実際に何を
     * 返しているかを確かめたい場合や、レスポンスをそのまま保存したい場合に使う。
     *
     * ```php
     * $response = $client->floorList();
     * $raw = $client->lastResponseBody();
     * ```
     *
     * 保持するのは直前の 1 件だけで、次の呼び出しで上書きされる。{@see self::buildUri()}
     * は送信しないので、値は変わらない。複数の呼び出しを並行させる場合は、
     * 呼び出しごとにインスタンスを分けること。
     */
    public function lastResponseBody(): ?string
    {
        return $this->httpClient->body();
    }

    /**
     * 商品情報 API (`/ItemList`)。
     *
     * @throws TransportException          HTTP 通信に失敗した場合
     * @throws ApiErrorException           API がエラーを返した場合
     * @throws MalformedResponseException  レスポンスが JSON として読めなかった場合
     * @throws ResponseValidationException レスポンスが期待する構造と一致しなかった場合
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
     * API を呼び出し、レスポンスボディを受け取ったままの文字列で返す。
     *
     * DTO への変換と検証は行わない。レスポンスをそのまま保存したい場合や、
     * API が実際に返している JSON を確認したい場合に使う。
     *
     * @throws TransportException HTTP 通信に失敗した場合
     * @throws ApiErrorException  API がエラーを返した場合
     */
    public function fetchRaw(Request $request): string
    {
        $httpRequest = $this->requestFactory
            ->createRequest('GET', $this->buildUri($request))
            ->withHeader('Accept', 'application/json');

        try {
            $httpResponse = $this->httpClient->sendRequest($httpRequest);
        } catch (ClientExceptionInterface $exception) {
            throw TransportException::fromClientException(
                $request->endpoint(),
                $exception,
                CredentialMasker::forCredentials($this->credentials),
            );
        }

        $body = (string) $httpResponse->getBody();
        $statusCode = $httpResponse->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw $this->createApiError($statusCode, $body);
        }

        return $body;
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
        $body = $this->fetchRaw($request);

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
        } catch (JsonException|ResponseValidationException) {
            return ApiErrorException::fromUnreadableBody($statusCode, $body);
        }

        return ApiErrorException::fromErrorResult($statusCode, $error->result, $body);
    }
}
