<?php

declare(strict_types=1);

namespace DmmApiClient\Exception;

use DmmApiClient\CredentialMasker;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * HTTP 通信自体に失敗したことを表す例外。
 *
 * 名前解決の失敗、接続タイムアウトなど、レスポンスを受け取れなかった場合に送出される。
 *
 * PSR-18 実装の例外メッセージには、送信先の URI がそのまま含まれることが多い
 * （例: Guzzle の `cURL error 6: ... for https://api.dmm.com/...?api_id=...`）。
 * この例外のメッセージをログに残しても認証情報が漏れないよう、取り込む際に伏せ字にする。
 *
 * ただし伏せ字にできるのはこの例外自身のメッセージだけで、`getPrevious()` が返す
 * もとの例外は HTTP クライアントのものなので、そのメッセージには手を加えられない。
 * `__toString()` は連鎖したすべての例外を連結するため、`(string) $e` や
 * `error_log($e)`、捕捉し損ねた場合の出力には伏せ字前の URI が現れる。
 * ログに残すなら `getMessage()` を使うこと。
 */
final class TransportException extends RuntimeException implements DmmApiClientException
{
    private function __construct(
        string $message,
        public readonly string $endpoint,
        ClientExceptionInterface $previous,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param CredentialMasker $masker もとの例外のメッセージに含まれる認証情報を伏せるマスカー
     */
    public static function fromClientException(
        string $endpoint,
        ClientExceptionInterface $previous,
        CredentialMasker $masker,
    ): self {
        return new self(
            sprintf('HTTP request to %s failed: %s', $endpoint, $masker->mask($previous->getMessage())),
            $endpoint,
            $previous,
        );
    }
}
