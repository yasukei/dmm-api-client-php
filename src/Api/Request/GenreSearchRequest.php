<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

/**
 * ジャンル検索 API (`/GenreSearch`) のリクエスト。
 *
 * 受け付けるパラメータは {@see FloorScopedSearchRequest} と同じで、
 * $initial はジャンル名かなの前方一致を表す。
 */
final readonly class GenreSearchRequest extends FloorScopedSearchRequest
{
    /** API のエンドポイントパス。 */
    public const string ENDPOINT = '/GenreSearch';

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }
}
