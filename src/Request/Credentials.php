<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

use DmmApiClient\Exception\InvalidArgumentException;

/**
 * DMM ウェブサービスの認証情報。
 */
final readonly class Credentials
{
    /**
     * アフィリエイト ID の形式は検証しない。
     *
     * 受け付ける形式は API 側の都合で決まり、将来広がることも狭まることもある。
     * 形式に合わない値は API がエラーを返すので、そちらに任せる。
     *
     * @param string $apiId       DMM ウェブサービスで発行された API ID
     * @param string $affiliateId アフィリエイト ID
     *
     * @throws InvalidArgumentException API ID が空の場合
     */
    public function __construct(
        public string $apiId,
        public string $affiliateId,
    ) {
        if ($apiId === '') {
            throw new InvalidArgumentException('api_id must not be empty.');
        }
    }

    /**
     * @return array{api_id: string, affiliate_id: string}
     */
    public function toQueryParameters(): array
    {
        return [
            'api_id' => $this->apiId,
            'affiliate_id' => $this->affiliateId,
        ];
    }
}
