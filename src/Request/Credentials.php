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
     * @param string $apiId          DMM ウェブサービスで発行された API ID
     * @param string $affiliateId    アフィリエイト ID。末尾が 990〜999 である必要がある（例: xxxxx-999）
     * @param bool   $validateFormat 形式を検証するか。通常は指定しない。
     *                               検証を外したい場合は {@see self::unchecked()} を使う
     *
     * @throws InvalidArgumentException 値が空、またはアフィリエイト ID の形式が不正な場合
     */
    public function __construct(
        public string $apiId,
        public string $affiliateId,
        bool $validateFormat = true,
    ) {
        if (! $validateFormat) {
            return;
        }

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
     * 形式を検証せずに認証情報を作る。
     *
     * 末尾が 990〜999 でないアフィリエイト ID を送ったときに API がどう応答するかなど、
     * 実際の挙動を確かめたい場合の逃げ道。通常は `new Credentials()` を使う。
     */
    public static function unchecked(string $apiId, string $affiliateId): self
    {
        return new self($apiId, $affiliateId, validateFormat: false);
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
