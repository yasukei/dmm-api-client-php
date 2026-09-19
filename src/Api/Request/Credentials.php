<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

use DmmApiClient\Api\Exception\InvalidArgumentException;

/**
 * DMM ウェブサービスの認証情報。
 */
final readonly class Credentials
{
    /**
     * @param string $apiId       DMM ウェブサービスで発行された API ID
     * @param string $affiliateId アフィリエイト ID
     *
     * @throws InvalidArgumentException $apiId または $affiliateId が空の場合
     */
    public function __construct(
        public string $apiId,
        public string $affiliateId,
    ) {
        if ($apiId === '') {
            throw new InvalidArgumentException('api_id must not be empty.');
        }

        if ($affiliateId === '') {
            throw new InvalidArgumentException('affiliate_id must not be empty.');
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
