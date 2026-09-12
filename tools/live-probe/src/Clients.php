<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Request\Credentials;
use Psr\Http\Client\ClientInterface;

/**
 * 対象を送るクライアントを、認証情報ごとに作り分ける。
 *
 * 認証情報はクライアントが持ち、リクエスト側からは差し替えられない
 * （{@see DmmApiClient::buildUri()} は認証情報を優先する）。わざとエラーを引くための対象は
 * 壊した認証情報で送る必要があるので、対象ごとにクライアントを選べるようにする。
 *
 * 同じ認証情報には同じクライアントを返す。HTTP クライアントを実行を通して使い回すため。
 */
final class Clients
{
    /** @var array<string, DmmApiClient> 認証情報ごとのクライアント */
    private array $clients = [];

    /**
     * @param Credentials          $credentials 実行に指定された認証情報
     * @param ClientInterface|null $httpClient  送信に使う PSR-18 クライアント。null なら自動検出する
     * @param string               $baseUri     API のベース URI
     */
    public function __construct(
        private readonly Credentials $credentials,
        private readonly ?ClientInterface $httpClient,
        private readonly string $baseUri,
    ) {
    }

    /**
     * 実行に指定された認証情報で送るクライアント。
     */
    public function primary(): DmmApiClient
    {
        return $this->forCredentials(null);
    }

    public function forTarget(Target $target): DmmApiClient
    {
        return $this->forCredentials($target->credentials);
    }

    /**
     * @param Credentials|null $credentials null なら実行に指定された認証情報を使う
     */
    private function forCredentials(?Credentials $credentials): DmmApiClient
    {
        $credentials ??= $this->credentials;
        $key = $credentials->apiId . "\0" . $credentials->affiliateId;

        return $this->clients[$key] ??= new DmmApiClient(
            $credentials,
            $this->httpClient,
            baseUri: $this->baseUri,
        );
    }
}
