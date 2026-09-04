<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\Request\ActressSearchRequest;
use DmmApiClient\Request\ActressSearchSort;
use DmmApiClient\Request\AuthorSearchRequest;
use DmmApiClient\Request\GenreSearchRequest;
use DmmApiClient\Request\ItemListRequest;
use DmmApiClient\Request\ItemListSort;
use DmmApiClient\Request\MakerSearchRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Request\SeriesSearchRequest;
use DmmApiClient\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Response\ItemList\ItemListResponse;
use DmmApiClient\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Response\SeriesSearch\SeriesSearchResponse;

/**
 * フロアの一覧と絞り込み条件から、叩く対象を組み立てる。
 */
final class Planner
{
    /** `--endpoint` に指定できる名前。 */
    public const array ENDPOINTS = [
        'FloorList',
        'ItemList',
        'ActressSearch',
        'GenreSearch',
        'MakerSearch',
        'SeriesSearch',
        'AuthorSearch',
    ];

    /**
     * offset の上限。API が返す検索結果は 50000 件までなので、それ以上は指定しない。
     */
    public const int OFFSET_MAX = 50000;

    /**
     * フロア ID で絞り込む 4 つの API。いずれもパラメータの形が同じ。
     *
     * @var array<string, array{class-string, class-string}>
     */
    private const array FLOOR_SCOPED = [
        'GenreSearch' => [GenreSearchRequest::class, GenreSearchResponse::class],
        'MakerSearch' => [MakerSearchRequest::class, MakerSearchResponse::class],
        'SeriesSearch' => [SeriesSearchRequest::class, SeriesSearchResponse::class],
        'AuthorSearch' => [AuthorSearchRequest::class, AuthorSearchResponse::class],
    ];

    /**
     * @param list<FloorRef> $floors
     *
     * @return list<Target>
     */
    public static function build(array $floors, Options $options): array
    {
        $targets = [];

        if ($options->wantsEndpoint('ActressSearch')) {
            $targets = [...$targets, ...self::actressSearch($options)];
        }

        foreach ($floors as $floor) {
            if (! $options->wantsFloor($floor)) {
                continue;
            }

            if ($options->wantsEndpoint('ItemList')) {
                $targets = [...$targets, ...self::itemList($floor, $options)];
            }

            if (! $options->wantsUnsortedEndpoints()) {
                continue;
            }

            foreach (self::FLOOR_SCOPED as $name => [$requestClass, $responseClass]) {
                if ($options->wantsEndpoint($name)) {
                    $targets[] = self::floorScoped($name, $requestClass, $responseClass, $floor, $options);
                }
            }
        }

        return $targets;
    }

    /**
     * @return list<Target>
     */
    private static function itemList(FloorRef $floor, Options $options): array
    {
        $hits = $options->hitsFor(ItemListRequest::HITS_MAX);
        $targets = [];

        foreach (ItemListSort::cases() as $sort) {
            if (! $options->wantsSort($sort->value)) {
                continue;
            }

            $targets[] = new Target(
                group: 'ItemList',
                endpoint: ItemListRequest::ENDPOINT,
                responseClass: ItemListResponse::class,
                key: $floor->key(),
                sort: $sort->value,
                hits: $hits,
                offsetMax: ItemListRequest::OFFSET_MAX,
                context: $floor->context(),
                build: static fn (int $offset): Request => new ItemListRequest(
                    site: $floor->site,
                    service: $floor->serviceCode,
                    floor: $floor->floorCode,
                    sort: $sort,
                    hits: $hits,
                    offset: $offset,
                ),
            );
        }

        return $targets;
    }

    /**
     * @return list<Target>
     */
    private static function actressSearch(Options $options): array
    {
        $hits = $options->hitsFor(ActressSearchRequest::HITS_MAX);
        $targets = [];

        foreach (ActressSearchSort::cases() as $sort) {
            if (! $options->wantsSort($sort->value)) {
                continue;
            }

            $targets[] = new Target(
                group: 'ActressSearch',
                endpoint: ActressSearchRequest::ENDPOINT,
                responseClass: ActressSearchResponse::class,
                key: 'all',
                sort: $sort->value,
                hits: $hits,
                offsetMax: self::OFFSET_MAX,
                context: [],
                build: static fn (int $offset): Request => new ActressSearchRequest(
                    sort: $sort,
                    hits: $hits,
                    offset: $offset,
                ),
            );
        }

        return $targets;
    }

    /**
     * @param class-string $requestClass  GenreSearchRequest と同じ引数を取るリクエスト
     * @param class-string $responseClass
     */
    private static function floorScoped(
        string $name,
        string $requestClass,
        string $responseClass,
        FloorRef $floor,
        Options $options,
    ): Target {
        // 4 つの API はいずれも (floorId, initial, hits, offset) を取る。
        $hits = $options->hitsFor(GenreSearchRequest::HITS_MAX);

        return new Target(
            group: $name,
            endpoint: '/' . $name,
            responseClass: $responseClass,
            key: $floor->key(),
            sort: null,
            hits: $hits,
            offsetMax: self::OFFSET_MAX,
            context: $floor->context(),
            build: static function (int $offset) use ($requestClass, $floor, $hits): Request {
                /** @var Request $request */
                $request = new $requestClass($floor->floorId, null, $hits, $offset);

                return $request;
            },
        );
    }
}
