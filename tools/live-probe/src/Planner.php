<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\Api\MonoStock;
use DmmApiClient\Api\Request\ActressSearchRequest;
use DmmApiClient\Api\Request\ActressSearchSort;
use DmmApiClient\Api\Request\ArticleFilter;
use DmmApiClient\Api\Request\ArticleType;
use DmmApiClient\Api\Request\AuthorSearchRequest;
use DmmApiClient\Api\Request\Credentials;
use DmmApiClient\Api\Request\FloorListRequest;
use DmmApiClient\Api\Request\GenreSearchRequest;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\ItemListSort;
use DmmApiClient\Api\Request\MakerSearchRequest;
use DmmApiClient\Api\Request\RawRequest;
use DmmApiClient\Api\Request\Request;
use DmmApiClient\Api\Request\SeriesSearchRequest;
use DmmApiClient\Api\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Api\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Api\Response\Error\ErrorResponse;
use DmmApiClient\Api\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\Api\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Api\Response\SeriesSearch\SeriesSearchResponse;

/**
 * フロアの一覧と絞り込み条件から、叩く対象を組み立てる。
 */
final class Planner
{
    /**
     * 商品情報 API の名前。
     *
     * エンドポイント名であると同時に、保存先のディレクトリ名でもあり、記録の group でもある。
     * {@see self::ARTICLES} や {@see self::MONO_STOCK} は保存済みの `ItemList` を
     * 読み直して組み立てるので、書き出す側と読む側で綴りが食い違うと黙って空振りする。
     */
    public const string ITEM_LIST = 'ItemList';

    /** `--endpoint` に指定できる名前。 */
    public const array ENDPOINTS = [
        'FloorList',
        self::ITEM_LIST,
        'ActressSearch',
        'GenreSearch',
        'MakerSearch',
        'SeriesSearch',
        'AuthorSearch',
        self::ERRORS,
    ];

    /**
     * `article` / `article_id` を指定して叩き直した分の保存先。
     *
     * `--endpoint` には並べない。単独では成り立たない処理なので、`ItemList` の一部として走る。
     *
     * 通常の `ItemList` とは別のディレクトリに置く。何を指定するかは保存済みの `ItemList` を
     * 数えて決めるので、絞り込んだレスポンスが同じ場所にあると、それも数に入ってしまう。
     */
    public const string ARTICLES = 'Articles';

    /**
     * `mono_stock` を指定して叩き直した分の保存先。
     *
     * `--endpoint` には並べない。`article` と同じく `ItemList` の一部として走る。
     */
    public const string MONO_STOCK = 'MonoStock';

    /** `mono_stock` を受け付けるサービスコード。ドキュメントに「通販サービスのみ指定可能」とある。 */
    private const string MONO_SERVICE = 'mono';

    /**
     * フロアごとに最初に叩く sort。
     *
     * 総件数が 1 ページに収まるフロアでは、これ 1 本で打ち切る（{@see Probe}）。sort を変えても
     * 同じ商品が並び替わるだけで、検証に使える形は増えないため。最初に叩くものを決めておけば、
     * 1 ページに収まると分かった時点で残りを止められる。
     */
    private const ItemListSort FIRST_SORT = ItemListSort::Date;

    /**
     * わざとエラーを引くための対象をまとめた、擬似的なエンドポイント名。
     *
     * API の名前ではないが、`--endpoint` で他と同じように選び分けられるようにする。
     */
    public const string ERRORS = 'Errors';

    /**
     * 発行されていないことが確実な API ID。
     *
     * 実在しない値であればよく、値そのものに意味は無い。何を送ったかがレポートから
     * 読み取れるよう、伏せ字にならない（＝実際の認証情報と重ならない）文字列にしている。
     */
    private const string INVALID_API_ID = 'INVALID_API_ID';

    /**
     * offset の上限。API が返す検索結果は 50000 件までなので、それ以上は指定しない。
     */
    private const int OFFSET_MAX = 50000;

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
     * @param Credentials    $credentials 実行に指定された認証情報。エラーを引く対象はこれを壊して使う
     *
     * @return list<Target>
     */
    public static function build(array $floors, Options $options, Credentials $credentials): array
    {
        $targets = [];

        if ($options->wantsEndpoint('ActressSearch')) {
            $targets = [...$targets, ...self::actressSearch($options)];
        }

        foreach ($floors as $floor) {
            if (! $options->wantsFloor($floor)) {
                continue;
            }

            if ($options->wantsEndpoint(self::ITEM_LIST)) {
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

        // エラーを引く対象は最後に回す。フロアの掃き出しが本題で、これはその付け足しのため。
        if ($options->wantsEndpoint(self::ERRORS) && $options->wantsUnsortedEndpoints()) {
            $targets = [...$targets, ...self::errorCases($credentials)];
        }

        return $targets;
    }

    /**
     * わざとエラーを引くための対象。
     *
     * 成功したレスポンスをどれだけ集めても {@see ErrorResponse} は一度も検証されない。
     * エラーの形が仕様どおりかを確かめるには、確実にエラーになるリクエストを送るしかない。
     *
     * 誤らせ方は認証情報とパラメータの 2 通り。パラメータの誤り（範囲外の `hits`、存在しない `floor`
     * など）はいずれも 400 に集約されるとみられるが、認証の誤りは別の形で返る可能性がある。
     * 両方を 1 件ずつ送って、実際に同じ形なのかを確かめる。
     * どれもフロアには依存しないので、実行全体で 1 件ずつあれば足りる。
     *
     * 認証情報を誤らせる対象は `FloorList` に送る。認証情報以外のパラメータを持たないため、
     * 返ってきたエラーの原因を認証情報だけに絞れる。
     *
     * なお認証情報は「送らない」ではなく「不正な値を送る」形にしている。{@see Credentials} は
     * `api_id` と `affiliate_id` を必ずクエリに載せるため、キーごと落とすことはできない。
     * どちらも API から見れば不正なリクエストで、エラーを引く目的には差が無い。
     *
     * @return list<Target>
     */
    private static function errorCases(Credentials $credentials): array
    {
        return [
            // 発行されていない API ID。エラーになることが最も確実で、認証の誤りがどう返るかを見る。
            self::errorCase(
                'invalid-api-id',
                new FloorListRequest(),
                new Credentials(self::INVALID_API_ID, $credentials->affiliateId),
            ),

            // 空のアフィリエイト ID。API ID とは別の形でエラーが返るかを見る。
            self::errorCase(
                'empty-affiliate-id',
                new FloorListRequest(),
                new Credentials($credentials->apiId, ''),
            ),

            // 必須パラメータの欠落。認証情報は正しいまま、`site` の無い `/ItemList` を送る。
            // 認証の誤りと同じ形で返るのか、`errors` にパラメータ名が入るのかを見る。
            // {@see ItemListRequest} は `site` を必ず載せるので、{@see RawRequest} で組み立てる。
            self::errorCase('missing-site', new RawRequest(ItemListRequest::ENDPOINT)),
        ];
    }

    /**
     * @param Credentials|null $credentials null なら実行に指定された認証情報をそのまま使う
     */
    private static function errorCase(string $case, Request $request, ?Credentials $credentials = null): Target
    {
        return new Target(
            group: self::ERRORS,
            endpoint: $request->endpoint(),
            responseClass: ErrorResponse::class,
            key: $case,
            sort: null,
            hits: null,
            offsetMax: 1,
            context: ['case' => $case],
            build: static fn (int $offset): Request => $request,
            credentials: $credentials,
            expectsError: true,
        );
    }

    /**
     * 分類と ID を 1 つ指定して叩く対象。
     *
     * 空振りしたときに別の ID で組み直せるよう、1 件ずつ作れる形にしてある。
     */
    public static function articleTarget(FloorRef $floor, string $article, string $id, Options $options): Target
    {
        $hits = $options->hitsFor(ItemListRequest::HITS_MAX);

        return new Target(
            group: self::ARTICLES,
            endpoint: ItemListRequest::ENDPOINT,
            responseClass: ItemListResponse::class,
            key: $floor->key() . '__article-' . Target::sanitize($article) . '-' . Target::sanitize($id),
            sort: null,
            hits: $hits,
            offsetMax: ItemListRequest::OFFSET_MAX,
            context: $floor->context() + ['article' => $article, 'article_id' => $id],
            build: static fn (int $offset): Request => self::articleRequest($floor, $article, $id, $hits, $offset),
            firstPageOnly: true,
        );
    }

    /**
     * 分類を複数まとめて指定して叩く対象。
     *
     * 見たいのは、複数指定したときのエコーバックが指定どおりに返るか（要素数と順序）と、
     * 絞り込みが重ねて効くか。1 つだけ指定する場合と同じ経路で扱えるよう、
     * 分類名と ID をそれぞれカンマで連ねて記録に残す。
     *
     * @param array<string, string> $articles 分類名 => ID。すべて {@see ArticleType} にあるもの
     */
    public static function articleComboTarget(FloorRef $floor, array $articles, Options $options): Target
    {
        $hits = $options->hitsFor(ItemListRequest::HITS_MAX);
        $filters = [];
        $parts = [];

        foreach ($articles as $article => $id) {
            $filters[] = new ArticleFilter(ArticleType::from($article), $id);
            $parts[] = Target::sanitize($article) . '-' . Target::sanitize($id);
        }

        return new Target(
            group: self::ARTICLES,
            endpoint: ItemListRequest::ENDPOINT,
            responseClass: ItemListResponse::class,
            key: $floor->key() . '__article-' . implode('_', $parts),
            sort: null,
            hits: $hits,
            offsetMax: ItemListRequest::OFFSET_MAX,
            context: $floor->context() + [
                'article' => implode(',', array_keys($articles)),
                'article_id' => implode(',', array_values($articles)),
            ],
            build: static fn (int $offset): Request => new ItemListRequest(
                site: $floor->site,
                service: $floor->serviceCode,
                floor: $floor->floorCode,
                articles: $filters,
                hits: $hits,
                offset: $offset,
            ),
            firstPageOnly: true,
        );
    }

    /**
     * {@see ArticleType} にある分類は {@see ItemListRequest} で組み立てる。ライブラリ自身の
     * article 組み立てを、実データで通せる唯一の経路になる。
     *
     * 無いものは {@see RawRequest} で送る。enum に足すのは絞り込みが効くと確かめてからで、
     * ドキュメントに無いものを、確かめる前に API として提供したくないため。
     */
    private static function articleRequest(
        FloorRef $floor,
        string $article,
        string $id,
        int $hits,
        int $offset,
    ): Request {
        $type = ArticleType::tryFrom($article);

        if ($type !== null) {
            return new ItemListRequest(
                site: $floor->site,
                service: $floor->serviceCode,
                floor: $floor->floorCode,
                articles: [new ArticleFilter($type, $id)],
                hits: $hits,
                offset: $offset,
            );
        }

        return new RawRequest(ItemListRequest::ENDPOINT, [
            'site' => $floor->site,
            'service' => $floor->serviceCode,
            'floor' => $floor->floorCode,
            'article' => [$article],
            'article_id' => [$id],
            'hits' => (string) $hits,
            'offset' => (string) $offset,
        ]);
    }

    /**
     * `mono_stock` の値を順に指定して、同じフロアを叩き直す対象。
     *
     * 通販（mono）のフロアだけが対象。ドキュメントが「通販サービスのみ指定可能」としており、
     * `stock` を返すのもそのフロアだけなので、他へ送っても確かめようがない。
     *
     * `article` と同じく、見たいのは「指定した値で絞り込めるか」なので先頭ページだけを取る。
     * 値は {@see MonoStock} の全種。絞り込みに使えないと分かっているものも含めて送る。
     * そう書いてあるだけで、全フロアで裏を取ったわけではないため。
     *
     * @return list<Target>
     */
    public static function monoStockTargets(FloorRef $floor, Options $options): array
    {
        if ($floor->serviceCode !== self::MONO_SERVICE) {
            return [];
        }

        $hits = $options->hitsFor(ItemListRequest::HITS_MAX);
        $targets = [];

        foreach (MonoStock::cases() as $stock) {
            $targets[] = new Target(
                group: self::MONO_STOCK,
                endpoint: ItemListRequest::ENDPOINT,
                responseClass: ItemListResponse::class,
                key: $floor->key() . '__mono_stock-' . Target::sanitize($stock->value),
                sort: null,
                hits: $hits,
                offsetMax: ItemListRequest::OFFSET_MAX,
                context: $floor->context() + ['mono_stock' => $stock->value],
                build: static fn (int $offset): Request => new ItemListRequest(
                    site: $floor->site,
                    service: $floor->serviceCode,
                    floor: $floor->floorCode,
                    monoStock: $stock,
                    hits: $hits,
                    offset: $offset,
                ),
                firstPageOnly: true,
            );
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

        foreach (self::sorts() as $sort) {
            if (! $options->wantsSort($sort->value)) {
                continue;
            }

            $targets[] = new Target(
                group: self::ITEM_LIST,
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
     * {@see self::FIRST_SORT} を先頭にした sort の並び。
     *
     * @return list<ItemListSort>
     */
    private static function sorts(): array
    {
        return [self::FIRST_SORT, ...array_values(array_filter(
            ItemListSort::cases(),
            static fn (ItemListSort $sort): bool => $sort !== self::FIRST_SORT,
        ))];
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
