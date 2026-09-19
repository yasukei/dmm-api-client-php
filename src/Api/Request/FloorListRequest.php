<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

/**
 * フロア検索 API (`/FloorList`) のリクエスト。
 *
 * 認証情報以外のパラメータを持たない。
 */
final readonly class FloorListRequest implements Request
{
    /** API のエンドポイントパス。 */
    public const string ENDPOINT = '/FloorList';

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toQueryParameters(): array
    {
        return [];
    }
}
