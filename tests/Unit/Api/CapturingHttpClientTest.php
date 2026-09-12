<?php

declare(strict_types=1);

use DmmApiClient\Api\CapturingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\NetworkFailure;
use Tests\Support\StubHttpClient;

function capturingClient(ClientInterface $inner): CapturingHttpClient
{
    return new CapturingHttpClient($inner, new Psr17Factory());
}

test('通したレスポンスの本文を控える', function (): void {
    $client = capturingClient(StubHttpClient::respondingWith(200, '{"result":{}}'));

    $client->sendRequest(new Request('GET', 'https://api.dmm.com/'));

    expect($client->body())->toBe('{"result":{}}');
});

test('控えたあとも、返したレスポンスから本文を先頭から読める', function (): void {
    $client = capturingClient(StubHttpClient::respondingWith(200, '{"result":{}}'));

    $response = $client->sendRequest(new Request('GET', 'https://api.dmm.com/'));

    // 本文を読むとストリームは終端まで進む。差し替えていないと、ここが空になる。
    expect($response->getBody()->getContents())->toBe('{"result":{}}');
});

test('応答を受け取っていなければ null を返す', function (): void {
    $client = capturingClient(StubHttpClient::respondingWith(200, '{}'));

    expect($client->body())->toBeNull();
});

test('控えるのは最後に通したレスポンスの本文', function (): void {
    $inner = new class () implements ClientInterface {
        private int $calls = 0;

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            return new Response(200, [], sprintf('{"call":%d}', ++$this->calls));
        }
    };
    $client = capturingClient($inner);

    $client->sendRequest(new Request('GET', 'https://api.dmm.com/'));
    $client->sendRequest(new Request('GET', 'https://api.dmm.com/'));

    expect($client->body())->toBe('{"call":2}');
});

test('通信に失敗した場合は生ボディを控えない', function (): void {
    $client = capturingClient(StubHttpClient::failingWith('connection refused'));

    expect(fn (): mixed => $client->sendRequest(new Request('GET', 'https://api.dmm.com/')))
        ->toThrow(NetworkFailure::class);

    expect($client->body())->toBeNull();
});
