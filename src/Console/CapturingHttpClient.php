<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * 通したレスポンスの本文を控えておく PSR-18 クライアント。
 *
 * コンソールは API が返した JSON をそのまま見せるため、本文を常に必要とする。
 * 一方 {@see \DmmApiClient\Api\DmmApiClient} の型付きメソッドは DTO を返すので、
 * 本文を受け取る口が無い。HTTP のところで控えておけば、呼び出し方に関わらず
 * 同じ本文が手に入る。
 *
 * @internal コンソール内部の都合で用意したもので、ライブラリの API ではない。
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

        // 本文を読むとストリームは終端まで進む。巻き戻せるとは限らないので、
        // 読み取った内容で新しいストリームに差し替えて返す。
        return $response->withBody($this->streamFactory->createStream($body));
    }

    /**
     * 最後に通したレスポンスの本文。
     *
     * 応答を 1 度も受け取っていなければ空文字を返す。呼び出し側にとって
     * 「本文が無い」と「本文が空」は同じ扱い（どちらも出力するものが無い）なので、
     * null と文字列を区別させない。
     */
    public function body(): string
    {
        return $this->body ?? '';
    }
}
