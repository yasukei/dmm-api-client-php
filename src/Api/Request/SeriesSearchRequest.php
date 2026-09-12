<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Request;

/**
 * シリーズ検索 API (`/SeriesSearch`) のリクエスト。
 *
 * 受け付けるパラメータは {@see FloorScopedSearchRequest} と同じで、
 * $initial はシリーズ名かなの前方一致を表す。
 */
final readonly class SeriesSearchRequest extends FloorScopedSearchRequest
{
    /** API のエンドポイントパス。 */
    public const string ENDPOINT = '/SeriesSearch';

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }
}
