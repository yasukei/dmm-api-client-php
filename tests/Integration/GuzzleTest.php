<?php

declare(strict_types=1);

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Exception\ApiErrorException;
use DmmApiClient\Api\Exception\TransportException;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\RawRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Tests\Support\Fixture;
use Tests\Support\LocalServer;

/*
 * 単体テストは Nyholm のメッセージを返すスタブで動かしている。ここでは README が勧める
 * Guzzle のクライアントとファクトリを組み合わせ、localhost のサーバーと実際に送受信する。
 */

function guzzleBackedClient(string $baseUri): DmmApiClient
{
    return new DmmApiClient(credentials(), $baseUri, new Client(), new HttpFactory());
}

function startFixtureServer(): LocalServer
{
    return LocalServer::start(__DIR__ . '/../Support/fixture-router.php');
}

test('Guzzle で送ったリクエストの応答をマッピングする', function (): void {
    $server = startFixtureServer();

    try {
        $response = guzzleBackedClient($server->baseUri)->itemList(new ItemListRequest(site: 'FANZA'));
    } finally {
        $server->stop();
    }

    expect($response->result->totalCount)->toBe(12450)
        ->and($response->body())->toBe(Fixture::json('item-list'));
});

test('Guzzle で受けたエラー応答を ApiErrorException にする', function (): void {
    $server = startFixtureServer();

    try {
        expect(fn(): string => guzzleBackedClient($server->baseUri)->fetchRaw(new RawRequest('/Unknown')))
            ->toThrow(ApiErrorException::class, 'DMM API returned 400 BAD REQUEST');
    } finally {
        $server->stop();
    }
});

test('Guzzle の通信失敗を TransportException にする', function (): void {
    $server = startFixtureServer();
    $server->stop();

    expect(fn(): mixed => guzzleBackedClient($server->baseUri)->itemList(new ItemListRequest(site: 'FANZA')))
        ->toThrow(TransportException::class);
});
