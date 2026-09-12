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

test('通したレスポンスの生ボディをキャプチャする', function (): void {
    $client = capturingClient(StubHttpClient::respondingWith(200, '{"result":{}}'));

    $client->sendRequest(new Request('GET', 'https://api.dmm.com/'));

    expect($client->body())->toBe('{"result":{}}');
});

test('キャプチャしたあとも、返したレスポンスから生ボディを先頭から読める', function (): void {
    $client = capturingClient(StubHttpClient::respondingWith(200, '{"result":{}}'));

    $response = $client->sendRequest(new Request('GET', 'https://api.dmm.com/'));

    // 生ボディを読むとストリームは終端まで進む。差し替えていないと、ここが空になる。
    expect($response->getBody()->getContents())->toBe('{"result":{}}');
});

test('レスポンスを受け取っていなければ null を返す', function (): void {
    $client = capturingClient(StubHttpClient::respondingWith(200, '{}'));

    expect($client->body())->toBeNull();
});

test('キャプチャするのは最後に通したレスポンスの生ボディ', function (): void {
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

test('通信に失敗した場合は生ボディをキャプチャしない', function (): void {
    $client = capturingClient(StubHttpClient::failingWith('connection refused'));

    expect(fn (): mixed => $client->sendRequest(new Request('GET', 'https://api.dmm.com/')))
        ->toThrow(NetworkFailure::class);

    expect($client->body())->toBeNull();
});

test('通信に失敗したら、前の送信でキャプチャした生ボディも残さない', function (): void {
    $inner = new class () implements ClientInterface {
        private int $calls = 0;

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            if (++$this->calls > 1) {
                throw new NetworkFailure('connection refused');
            }

            return new Response(200, [], '{"call":1}');
        }
    };
    $client = capturingClient($inner);

    $client->sendRequest(new Request('GET', 'https://api.dmm.com/'));

    expect(fn (): mixed => $client->sendRequest(new Request('GET', 'https://api.dmm.com/')))
        ->toThrow(NetworkFailure::class);

    expect($client->body())->toBeNull();
});
