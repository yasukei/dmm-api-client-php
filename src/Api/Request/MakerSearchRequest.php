<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

/**
 * メーカー検索 API (`/MakerSearch`) のリクエスト。
 *
 * 受け付けるパラメータは {@see FloorScopedSearchRequest} と同じで、
 * $initial はメーカー名かなの前方一致を表す。
 */
final readonly class MakerSearchRequest extends FloorScopedSearchRequest
{
    /** API のエンドポイントパス。 */
    public const string ENDPOINT = '/MakerSearch';

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }
}
