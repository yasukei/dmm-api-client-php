<?php

declare(strict_types=1);

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\DmmApiClientInterface;
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
use Tests\Support\Fixture;
use Tests\Support\StubHttpClient;

/**
 * 利用側のテストで書くことを想定したフェイク。保存済みの JSON をマッピングして返す。
 */
function fakeDmmApiClient(): DmmApiClientInterface
{
    return new class implements DmmApiClientInterface {
        public function itemList(ItemListRequest $request): ItemListResponse
        {
            return responseMapper()->itemList(Fixture::decoded('item-list'));
        }

        public function floorList(FloorListRequest $request = new FloorListRequest()): FloorListResponse
        {
            return responseMapper()->floorList(Fixture::decoded('floor-list'));
        }

        public function actressSearch(ActressSearchRequest $request): ActressSearchResponse
        {
            return responseMapper()->actressSearch(Fixture::decoded('actress-search'));
        }

        public function genreSearch(GenreSearchRequest $request): GenreSearchResponse
        {
            return responseMapper()->genreSearch(Fixture::decoded('genre-search'));
        }

        public function makerSearch(MakerSearchRequest $request): MakerSearchResponse
        {
            return responseMapper()->makerSearch(Fixture::decoded('maker-search'));
        }

        public function seriesSearch(SeriesSearchRequest $request): SeriesSearchResponse
        {
            return responseMapper()->seriesSearch(Fixture::decoded('series-search'));
        }

        public function authorSearch(AuthorSearchRequest $request): AuthorSearchResponse
        {
            return responseMapper()->authorSearch(Fixture::decoded('author-search'));
        }
    };
}

test('DmmApiClient は DmmApiClientInterface を実装する', function (): void {
    expect(new DmmApiClient(credentials(), httpClient: StubHttpClient::respondingWith(200, '{}')))
        ->toBeInstanceOf(DmmApiClientInterface::class);
});

test('フェイクに差し替えられる', function (): void {
    $client = fakeDmmApiClient();

    expect($client->itemList(new ItemListRequest(site: 'FANZA')))->toBeInstanceOf(ItemListResponse::class)
        ->and($client->floorList())->toBeInstanceOf(FloorListResponse::class);
});

test('ResponseMapper で作った DTO は生ボディを持たない', function (): void {
    $response = fakeDmmApiClient()->itemList(new ItemListRequest(site: 'FANZA'));

    expect(fn(): string => $response->body())->toThrow(LogicException::class);
});
