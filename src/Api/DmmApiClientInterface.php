<?php

declare(strict_types=1);

namespace DmmApiClient\Api;

use DmmApiClient\Api\Exception\ApiErrorException;
use DmmApiClient\Api\Exception\MalformedResponseException;
use DmmApiClient\Api\Exception\ResponseValidationException;
use DmmApiClient\Api\Exception\TransportException;
use DmmApiClient\Api\Request\ActressSearchRequest;
use DmmApiClient\Api\Request\AuthorSearchRequest;
use DmmApiClient\Api\Request\FloorListRequest;
use DmmApiClient\Api\Request\GenreSearchRequest;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\MakerSearchRequest;
use DmmApiClient\Api\Request\SeriesSearchRequest;
use DmmApiClient\Api\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Api\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Api\Response\FloorList\FloorListResponse;
use DmmApiClient\Api\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\Api\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Api\Response\SeriesSearch\SeriesSearchResponse;

/**
 * DMM ウェブサービス API v3 の各エンドポイントを呼び出し、DTO で受け取る。
 *
 * 利用側のテストで {@see DmmApiClient} をフェイクに差し替えるためのもの。
 * フェイクが返す DTO は、保存済みの JSON を {@see \DmmApiClient\Api\Response\ResponseMapper}
 * でマッピングすれば作れる。ただしそうして作った DTO は生ボディを持たない
 * （{@see \DmmApiClient\Api\Response\Common\RawBodyAware}）。
 *
 * 型付きのメソッドだけを持つ。{@see DmmApiClient::buildUri()} / {@see DmmApiClient::fetchRaw()} は含めない。
 */
interface DmmApiClientInterface
{
    /**
     * 商品情報 API (`/ItemList`)。
     *
     * @throws TransportException          HTTP 通信に失敗した場合
     * @throws ApiErrorException           API がエラーを返した場合
     * @throws MalformedResponseException  レスポンスが JSON として読めなかった場合
     * @throws ResponseValidationException レスポンスが期待する構造と一致しなかった場合
     */
    public function itemList(ItemListRequest $request): ItemListResponse;

    /**
     * フロア検索 API (`/FloorList`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function floorList(FloorListRequest $request = new FloorListRequest()): FloorListResponse;

    /**
     * 女優検索 API (`/ActressSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function actressSearch(ActressSearchRequest $request): ActressSearchResponse;

    /**
     * ジャンル検索 API (`/GenreSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function genreSearch(GenreSearchRequest $request): GenreSearchResponse;

    /**
     * メーカー検索 API (`/MakerSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function makerSearch(MakerSearchRequest $request): MakerSearchResponse;

    /**
     * シリーズ検索 API (`/SeriesSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function seriesSearch(SeriesSearchRequest $request): SeriesSearchResponse;

    /**
     * 作者検索 API (`/AuthorSearch`)。
     *
     * @throws TransportException|ApiErrorException|MalformedResponseException|ResponseValidationException
     */
    public function authorSearch(AuthorSearchRequest $request): AuthorSearchResponse;
}
