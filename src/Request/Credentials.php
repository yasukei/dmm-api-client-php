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
     * @param string $apiId       DMM ウェブサービスで発行された API ID
     * @param string $affiliateId アフィリエイト ID。末尾が 990〜999 である必要がある（例: xxxxx-999）
     *
     * @throws InvalidArgumentException 値が空、またはアフィリエイト ID の形式が不正な場合
     */
    public function __construct(
        public string $apiId,
        public string $affiliateId,
    ) {
        if ($apiId === '') {
            throw new InvalidArgumentException('api_id must not be empty.');
        }

        if (preg_match('/-99[0-9]$/', $affiliateId) !== 1) {
            throw new InvalidArgumentException(
                sprintf('affiliate_id must end with 990-999, "%s" given.', $affiliateId),
            );
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
