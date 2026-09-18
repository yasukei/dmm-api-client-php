<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

/**
 * 作者検索 API (`/AuthorSearch`) のリクエスト。
 *
 * 受け付けるパラメータは {@see FloorScopedSearchRequest} と同じで、
 * $initial は作者名かなの前方一致を表す。
 */
final readonly class AuthorSearchRequest extends FloorScopedSearchRequest
{
    /** API のエンドポイントパス。 */
    public const string ENDPOINT = '/AuthorSearch';

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }
}
