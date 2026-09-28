<?php

declare(strict_types=1);

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Exception\ApiErrorException;
use DmmApiClient\Api\Exception\TransportException;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\RawRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Tests\Support\Fixture;
use Tests\Support\LocalServer;

/*
 * `composer require guzzlehttp/guzzle` だけを入れた利用者と同じく、PSR-18 クライアントと
 * PSR-17 ファクトリを自動検出に任せ、localhost のサーバーと実際に送受信する。
 */

function discoveredClient(string $baseUri): DmmApiClient
{
    return new DmmApiClient(credentials(), $baseUri);
}

function startFixtureServer(): LocalServer
{
    return LocalServer::start(__DIR__ . '/../Support/fixture-router.php');
}

test('自動検出は Guzzle のクライアントとファクトリを選ぶ', function (): void {
    expect(Psr18ClientDiscovery::find())->toBeInstanceOf(Client::class)
        ->and(Psr17FactoryDiscovery::findRequestFactory())->toBeInstanceOf(HttpFactory::class);
});

test('Guzzle で送ったリクエストの応答をマッピングする', function (): void {
    $server = startFixtureServer();

    try {
        $response = discoveredClient($server->baseUri)->itemList(new ItemListRequest(site: 'FANZA'));
    } finally {
        $server->stop();
    }

    expect($response->result->totalCount)->toBe(12450)
        ->and($response->body())->toBe(Fixture::json('item-list'));
});

test('Guzzle で受けたエラー応答を ApiErrorException にする', function (): void {
    $server = startFixtureServer();

    try {
        expect(fn(): string => discoveredClient($server->baseUri)->fetchRaw(new RawRequest('/Unknown')))
            ->toThrow(ApiErrorException::class, 'DMM API returned 400 BAD REQUEST');
    } finally {
        $server->stop();
    }
});

test('Guzzle の通信失敗を、認証情報を伏せた TransportException にする', function (): void {
    $server = startFixtureServer();
    $server->stop();

    $exception = catchThrown(
        TransportException::class,
        fn(): mixed => discoveredClient($server->baseUri)->itemList(new ItemListRequest(site: 'FANZA')),
    );

    expect($exception->getMessage())->not->toContain('MY_API_ID')
        ->and($exception->getMessage())->not->toContain('myaffiliateid-999');
});
