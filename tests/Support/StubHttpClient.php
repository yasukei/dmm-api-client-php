<?php

declare(strict_types=1);

namespace Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * 決め打ちのレスポンスを返す PSR-18 クライアント。送られたリクエストを記録する。
 */
final class StubHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> 送信されたリクエスト */
    public array $requests = [];

    private function __construct(
        private readonly ResponseInterface|ClientExceptionInterface $result,
    ) {
    }

    public static function respondingWith(int $statusCode, string $body, string $contentType = 'application/json'): self
    {
        return new self(new Response($statusCode, ['Content-Type' => $contentType], $body));
    }

    public static function respondingWithFixture(string $name, int $statusCode = 200): self
    {
        return self::respondingWith($statusCode, Fixture::json($name));
    }

    public static function failingWith(string $message): self
    {
        return new self(new NetworkFailure($message));
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->result instanceof ClientExceptionInterface) {
            throw $this->result;
        }

        return $this->result;
    }

    public function lastRequest(): RequestInterface
    {
        $request = end($this->requests);

        if ($request === false) {
            throw new RuntimeException('No request has been sent yet.');
        }

        return $request;
    }

    /**
     * 最後に送信されたリクエストのクエリ文字列を、デコード済みの連想配列で返す。
     *
     * @return array<int|string, array<mixed>|string>
     */
    public function lastQueryParameters(): array
    {
        parse_str($this->lastRequest()->getUri()->getQuery(), $parameters);

        return $parameters;
    }
}
