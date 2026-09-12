<?php

declare(strict_types=1);

namespace DmmApiClient\Api;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * レスポンスの生ボディをキャプチャする PSR-18 クライアント。
 *
 * {@see DmmApiClient} の型付きメソッドは DTO を返すため、生ボディを受け取る口が無い。
 * HTTP のところで控えておけば、呼び出し方に関わらず同じ生ボディが手に入る。
 * {@see DmmApiClient::lastResponseBody()} がこれを読む。
 *
 * @internal {@see DmmApiClient} が内部で使うためのもので、単体で使うことは想定していない。
 */
final class CapturingHttpClient implements ClientInterface
{
    private ?string $body = null;

    public function __construct(
        private readonly ClientInterface $inner,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $response = $this->inner->sendRequest($request);
        $body = (string) $response->getBody();
        $this->body = $body;

        // 生ボディを読むとストリームは終端まで進む。巻き戻せるとは限らないので、
        // 読み取った内容で新しいストリームに差し替えて返す。
        return $response->withBody($this->streamFactory->createStream($body));
    }

    /**
     * 最後にキャプチャした生ボディ。応答を 1 度も受け取っていなければ null。
     */
    public function body(): ?string
    {
        return $this->body;
    }
}
