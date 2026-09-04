<?php

declare(strict_types=1);

use DmmApiClient\Exception\ResponseValidationException;
use DmmApiClient\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Response\Error\ErrorResponse;
use DmmApiClient\Response\FloorList\FloorListResponse;
use DmmApiClient\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Response\ItemList\ItemListResponse;
use DmmApiClient\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Response\SeriesSearch\SeriesSearchResponse;
use DmmApiClient\SiteCode;
use Tests\Support\Fixture;

test('商品情報のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->itemList(Fixture::decoded('item-list'));

    expect($response)->toBeInstanceOf(ItemListResponse::class)
        ->and($response->result->status)->toBe(200)
        ->and($response->result->resultCount)->toBe(2)
        ->and($response->result->totalCount)->toBe(12450)
        ->and($response->result->firstPosition)->toBe(1)
        ->and($response->result->items)->toHaveCount(2);

    $item = $response->result->items[0];

    expect($item->contentId)->toBe('mizd00320')
        ->and($item->title)->toBe('サンプル動画作品')
        ->and($item->serviceCode)->toBe('digital')
        ->and($item->floorCode)->toBe('videoa')
        ->and($item->volume)->toBe('120')
        ->and($item->url)->toBe('https://video.dmm.co.jp/av/content/?id=mizd00320')
        ->and($item->affiliateUrl)->toStartWith('https://al.dmm.co.jp/');
});

test('商品のレビュー・画像・サンプルをマッピングする', function (): void {
    $item = responseMapper()->itemList(Fixture::decoded('item-list'))->result->items[0];

    expect($item->review?->count)->toBe(12)
        ->and($item->review?->average)->toBe('4.20')
        ->and($item->imageUrl?->list)->toContain('mizd00320pt.jpg')
        ->and($item->imageUrl?->large)->toContain('mizd00320pl.jpg')
        ->and($item->sampleImageUrl?->sampleS?->image)->toHaveCount(2)
        ->and($item->sampleImageUrl?->sampleL?->image)->toHaveCount(1)
        ->and($item->sampleMovieUrl?->pcFlag)->toBe(1)
        ->and($item->sampleMovieUrl?->spFlag)->toBe(1)
        ->and($item->sampleMovieUrl?->size476x306)->toContain('size=476_306')
        ->and($item->sampleMovieUrl?->size644x414)->toContain('size=644_414');
});

test('返らなかったサイズのサンプル動画 URL は null になる', function (): void {
    // 実データで確認できたのは 476_306 / 560_360 / 720_480 の 3 つ。
    // 仕様に載っている 644_414 が返らない商品でもマッピングできること。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'sampleMovieURL'], [
        'size_476_306' => 'https://www.dmm.co.jp/litevideo/-/part/=/size=476_306/',
        'size_560_360' => 'https://www.dmm.co.jp/litevideo/-/part/=/size=560_360/',
        'size_720_480' => 'https://www.dmm.co.jp/litevideo/-/part/=/size=720_480/',
        'pc_flag' => 1,
        'sp_flag' => 1,
    ]);

    $sampleMovieUrl = responseMapper()->itemList($payload)->result->items[0]->sampleMovieUrl;

    expect($sampleMovieUrl?->size476x306)->toContain('size=476_306')
        ->and($sampleMovieUrl?->size560x360)->toContain('size=560_360')
        ->and($sampleMovieUrl?->size720x480)->toContain('size=720_480')
        ->and($sampleMovieUrl?->size644x414)->toBeNull();
});

test('価格を API が返す文字列のまま保持する', function (): void {
    $prices = responseMapper()->itemList(Fixture::decoded('item-list'))->result->items[0]->prices;

    expect($prices?->price)->toBe('300~')
        ->and($prices?->listPrice)->toBeNull()
        ->and($prices?->deliveries?->delivery)->toHaveCount(3)
        ->and($prices?->deliveries?->delivery[0]->type)->toBe('stream')
        ->and($prices?->deliveries?->delivery[0]->price)->toBe('300')
        ->and($prices?->deliveries?->delivery[0]->listPrice)->toBe('500');
});

test('大サイズだけのサンプル画像もマッピングできる', function (): void {
    // 同人のフロアは sample_l だけを返し、sample_s はキーごと落とす。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'sampleImageURL'], [
        'sample_l' => ['image' => ['https://pics.dmm.co.jp/digital/doujin/sample-1.jpg']],
    ]);

    $sampleImageUrl = responseMapper()->itemList($payload)->result->items[0]->sampleImageUrl;

    expect($sampleImageUrl?->sampleS)->toBeNull()
        ->and($sampleImageUrl?->sampleL?->image)->toHaveCount(1);
});

test('巻数を API が返す文字列のまま保持する', function (): void {
    // number を返すのは電子書籍のフロアだけで、実データでは常に "1" のような文字列。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [[
        'service_code' => 'ebook',
        'service_name' => '電子書籍',
        'floor_code' => 'comic',
        'floor_name' => 'コミック',
        'category_name' => 'コミック',
        'content_id' => 'b123asample00001',
        'title' => 'サンプルコミック',
        'URL' => 'https://book.dmm.co.jp/product/000000/b123asample00001/',
        'affiliateURL' => 'https://al.dmm.co.jp/?lurl=example&af_id=myaffiliateid-999',
        'volume' => '180',
        'number' => '3',
    ]]);

    expect(responseMapper()->itemList($payload)->result->items[0]->number)->toBe('3');
});

test('ゼロが数値で返る価格もマッピングできる', function (): void {
    // 無料の同人作品は price が "0"、list_price が 0 と、同じ商品の中で書き方が割れる。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'prices'], [
        'price' => '0',
        'list_price' => 0,
        'deliveries' => ['delivery' => [['type' => 'download', 'price' => '0', 'list_price' => 0]]],
    ]);

    $prices = responseMapper()->itemList($payload)->result->items[0]->prices;

    expect($prices?->price)->toBe('0')
        ->and($prices?->listPrice)->toBe(0)
        ->and($prices?->deliveries?->delivery[0]->listPrice)->toBe(0);
});

test('価格が数値のみの商品もマッピングできる', function (): void {
    // 単品価格を持たない商品（セット販売や配信の見放題）は prices が {"price": 0} だけになる。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'prices'], ['price' => 0]);

    $prices = responseMapper()->itemList($payload)->result->items[0]->prices;

    expect($prices?->price)->toBe(0)
        ->and($prices?->listPrice)->toBeNull()
        ->and($prices?->deliveries)->toBeNull();
});

test('発売日を DateTimeImmutable に変換する', function (): void {
    $date = responseMapper()->itemList(Fixture::decoded('item-list'))->result->items[0]->date;

    expect($date)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($date?->format('Y-m-d H:i:s'))->toBe('2023-10-15 10:00:00');
});

test('iteminfo をマッピングし、無いキーは空配列にする', function (): void {
    $itemInfo = responseMapper()->itemList(Fixture::decoded('item-list'))->result->items[0]->iteminfo;

    expect($itemInfo?->genre)->toHaveCount(2)
        ->and($itemInfo?->genre[0]->id)->toBe(6533)
        ->and($itemInfo?->genre[0]->name)->toBe('ハイビジョン')
        ->and($itemInfo?->actress[0]->name)->toBe('サンプル女優')
        ->and($itemInfo?->author)->toBe([])
        ->and($itemInfo?->manufacture)->toBe([])
        ->and($itemInfo?->color)->toBe([]);
});

test('CD のアーティストと読み仮名をマッピングする', function (): void {
    // 読み仮名は人物系だけが持ち、1 商品に複数のアーティストが並ぶこともある。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'iteminfo', 'artist'], [
        ['id' => 188080, 'name' => 'サンプルアーティスト', 'ruby' => 'さんぷるあーてぃすと'],
        ['id' => 83163, 'name' => 'サンプルバンド', 'ruby' => 'さんぷるばんど'],
    ]);

    $itemInfo = responseMapper()->itemList($payload)->result->items[0]->iteminfo;

    expect($itemInfo?->artist)->toHaveCount(2)
        ->and($itemInfo?->artist[0]->name)->toBe('サンプルアーティスト')
        ->and($itemInfo?->artist[0]->ruby)->toBe('さんぷるあーてぃすと')
        // ジャンルは読み仮名を持たない。
        ->and($itemInfo?->genre[0]->ruby)->toBeNull();
});

test('電子書籍の出版社をマッピングする', function (): void {
    // manufacture は電子書籍のフロアだけが返し、常に 1 件だけ入っている。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'iteminfo', 'manufacture'], [
        ['id' => 93514, 'name' => '集英社'],
    ]);

    $itemInfo = responseMapper()->itemList($payload)->result->items[0]->iteminfo;

    expect($itemInfo?->manufacture)->toHaveCount(1)
        ->and($itemInfo?->manufacture[0]->id)->toBe(93514)
        ->and($itemInfo?->manufacture[0]->name)->toBe('集英社');
});

test('title と URL が欠けている商品もマッピングできる', function (): void {
    // 登録漏れと思われる商品が実在する。その場合 affiliateURL も lurl= が空で返る。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 1], [
        'service_code' => 'dmmtv',
        'service_name' => 'DMMTV',
        'floor_code' => 'dmmtv_video',
        'floor_name' => 'DMMTV',
        'category_name' => 'DMMTV',
        'content_id' => '5267mytheater00019',
        'affiliateURL' => 'https://al.dmm.com/?lurl=&af_id=myaffiliateid-999&ch=api',
    ]);

    $item = responseMapper()->itemList($payload)->result->items[1];

    expect($item->contentId)->toBe('5267mytheater00019')
        ->and($item->title)->toBeNull()
        ->and($item->url)->toBeNull()
        ->and($item->affiliateUrl)->toContain('lurl=&');
});

test('メーカーの「その他」枠は文字列の ID で返る', function (): void {
    // 実在のメーカーを指す ID ではなく、該当なしを表す区分。数値 ID のメーカーに続けて並ぶ。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'iteminfo', 'maker'], [
        ['id' => 10016, 'name' => 'サンプルメーカー'],
        ['id' => 'other', 'name' => 'その他'],
    ]);

    $itemInfo = responseMapper()->itemList($payload)->result->items[0]->iteminfo;

    expect($itemInfo?->maker)->toHaveCount(2)
        ->and($itemInfo?->maker[0]->id)->toBe(10016)
        ->and($itemInfo?->maker[1]->id)->toBe('other');
});

test('任意項目が無い商品もマッピングできる', function (): void {
    $item = responseMapper()->itemList(Fixture::decoded('item-list'))->result->items[1];

    expect($item->contentId)->toBe('n_709sample01')
        ->and($item->productId)->toBeNull()
        ->and($item->volume)->toBeNull()
        ->and($item->number)->toBeNull()
        ->and($item->review)->toBeNull()
        ->and($item->imageUrl)->toBeNull()
        ->and($item->tachiyomi)->toBeNull()
        ->and($item->sampleImageUrl)->toBeNull()
        ->and($item->sampleMovieUrl)->toBeNull()
        ->and($item->prices)->toBeNull()
        ->and($item->date)->toBeNull()
        ->and($item->iteminfo)->toBeNull();
});

test('検索結果が 0 件でも items が空配列になる', function (): void {
    $response = responseMapper()->itemList(Fixture::decoded('item-list-empty'));

    expect($response->result->totalCount)->toBe(0)
        ->and($response->result->items)->toBe([]);
});

test('リクエストのエコーバックをマッピングする', function (): void {
    $request = responseMapper()->itemList(Fixture::decoded('item-list'))->request;

    expect($request?->parameters)
        ->toHaveKey('api_id', 'MY_API_ID')
        ->toHaveKey('site', 'FANZA');
});

test('配列で送ったパラメータは配列のままエコーバックされる', function (): void {
    // article と article_id は article[0]=genre の形で送るため、返りも配列になる。
    $payload = Fixture::decodedWith('item-list', ['request', 'parameters'], [
        'api_id' => 'MY_API_ID',
        'site' => 'DMM.com',
        'article' => ['genre'],
        'article_id' => ['15226'],
    ]);

    $request = responseMapper()->itemList($payload)->request;

    expect($request?->parameters)
        ->toHaveKey('article', ['genre'])
        ->toHaveKey('article_id', ['15226'])
        ->toHaveKey('api_id', 'MY_API_ID');
});

test('name/value 形式のエコーバックは検証エラーにする', function (): void {
    // 実際には返らない形式なので受け付けない。判断が誤っていれば例外で気づける。
    $payload = Fixture::decodedWith('item-list', ['request', 'parameters'], [
        ['name' => 'api_id', 'value' => 'MY_API_ID'],
    ]);

    expect(fn (): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

test('フロア構成のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->floorList(Fixture::decoded('floor-list'));

    expect($response)->toBeInstanceOf(FloorListResponse::class)
        ->and($response->result->site)->toHaveCount(2)
        ->and($response->result->site[0]->code)->toBe(SiteCode::DmmCom)
        ->and($response->result->site[0]->service)->toHaveCount(2)
        ->and($response->result->site[0]->service[0]->code)->toBe('digital')
        ->and($response->result->site[0]->service[0]->floor)->toHaveCount(2)
        ->and($response->result->site[0]->service[0]->floor[0]->id)->toBe('40')
        ->and($response->result->site[0]->service[0]->floor[0]->code)->toBe('videoc')
        ->and($response->result->site[1]->code)->toBe(SiteCode::Fanza);
});

test('女優検索のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->actressSearch(Fixture::decoded('actress-search'));

    expect($response)->toBeInstanceOf(ActressSearchResponse::class)
        // 女優検索は first_position も文字列で返す。他の検索 API は数値。
        ->and($response->result->totalCount)->toBe('3421')
        ->and($response->result->firstPosition)->toBe('1')
        ->and($response->result->resultCount)->toBe(2)
        ->and($response->result->actress)->toHaveCount(2);

    $actress = $response->result->actress[0];

    expect($actress->id)->toBe('1078970')
        ->and($actress->name)->toBe('サンプル女優')
        ->and($actress->ruby)->toBe('さんぷるじょゆう')
        ->and($actress->bust)->toBe('90')
        ->and($actress->cup)->toBe('G')
        ->and($actress->height)->toBe('160')
        ->and($actress->birthday?->format('Y-m-d H:i:s'))->toBe('1995-04-01 00:00:00')
        ->and($actress->bloodType)->toBe('A')
        ->and($actress->prefectures)->toBe('東京都')
        ->and($actress->imageUrl?->large)->toContain('actjpgs/sample.jpg')
        ->and($actress->listUrl?->digital)->toContain('lurl=digital');
});

test('プロフィール未登録の女優もマッピングできる', function (): void {
    $actress = responseMapper()->actressSearch(Fixture::decoded('actress-search'))->result->actress[1];

    expect($actress->name)->toBe('プロフィール未登録')
        ->and($actress->bust)->toBeNull()
        ->and($actress->cup)->toBeNull()
        ->and($actress->birthday)->toBeNull()
        ->and($actress->imageUrl)->toBeNull()
        ->and($actress->listUrl)->toBeNull();
});

test('ジャンル検索のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->genreSearch(Fixture::decoded('genre-search'));

    expect($response)->toBeInstanceOf(GenreSearchResponse::class)
        // 商品情報 API は数値で返すが、検索系は文字列で返す。
        ->and($response->result->status)->toBe('200')
        ->and($response->result->totalCount)->toBe('87')
        ->and($response->result->firstPosition)->toBe(1)
        ->and($response->result->siteCode)->toBe(SiteCode::Fanza)
        ->and($response->result->siteName)->toBe('FANZA（アダルト）')
        ->and($response->result->serviceCode)->toBe('digital')
        ->and($response->result->floorId)->toBe('43')
        ->and($response->result->floorCode)->toBe('videoa')
        ->and($response->result->genre)->toHaveCount(2)
        ->and($response->result->genre[0]->genreId)->toBe('6533')
        ->and($response->result->genre[0]->name)->toBe('ハイビジョン')
        ->and($response->result->genre[0]->ruby)->toBe('はいびじょん')
        ->and($response->result->genre[0]->listUrl)->toContain('af_id=myaffiliateid-999');
});

test('検索結果が 0 件のときの total_count は数値で返る', function (): void {
    // 0 件のときだけ数値、それ以外は文字列。値によって型が変わるので、両方受け付ける。
    $payload = Fixture::decodedWith('genre-search', ['result', 'total_count'], 0);

    expect(responseMapper()->genreSearch($payload)->result->totalCount)->toBe(0);
});

test('list_url が返らないフロアの結果もマッピングできる', function (): void {
    // 一覧ページを持たないフロアでは、list_url が null ではなくキーごと返らない。
    // どのフロアで返らないかは API 側の都合で、同じ ID でもフロアによって変わる。
    $payload = Fixture::decodedWithout('genre-search', ['result', 'genre', 0, 'list_url']);

    expect(responseMapper()->genreSearch($payload)->result->genre[0]->listUrl)->toBeNull();
});

test('メーカー検索のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->makerSearch(Fixture::decoded('maker-search'));

    expect($response)->toBeInstanceOf(MakerSearchResponse::class)
        ->and($response->result->maker)->toHaveCount(2)
        ->and($response->result->maker[0]->makerId)->toBe('45276')
        ->and($response->result->maker[0]->name)->toBe('サンプルメーカー');
});

test('シリーズ検索のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->seriesSearch(Fixture::decoded('series-search'));

    expect($response)->toBeInstanceOf(SeriesSearchResponse::class)
        ->and($response->result->series)->toHaveCount(2)
        ->and($response->result->series[0]->seriesId)->toBe('216861')
        ->and($response->result->series[0]->name)->toBe('サンプルシリーズ');
});

test('作者検索のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->authorSearch(Fixture::decoded('author-search'));

    expect($response)->toBeInstanceOf(AuthorSearchResponse::class)
        ->and($response->result->floorCode)->toBe('digital_doujin')
        ->and($response->result->author)->toHaveCount(2)
        ->and($response->result->author[0]->authorId)->toBe('21414')
        ->and($response->result->author[0]->anotherName)->toBe('別名義/アナザーネーム')
        ->and($response->result->author[1]->anotherName)->toBeNull()
        ->and($response->result->author[1]->listUrl)->toContain('af_id=');
});

test('エラーレスポンスをマッピングする', function (): void {
    $response = responseMapper()->error(Fixture::decoded('error'));

    expect($response)->toBeInstanceOf(ErrorResponse::class)
        ->and($response->result->status)->toBe(400)
        ->and($response->result->message)->toBe('BAD REQUEST')
        ->and($response->result->errors)->toBe(['affiliate_id' => 'Invalid Request Error']);
});

test('DMM 側で項目が増えてもマッピングは壊れない', function (): void {
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'brand_new_field'], 'something');
    $payload['brand_new_top_level'] = ['nested' => true];

    expect(responseMapper()->itemList($payload)->result->status)->toBe(200);
});

test('型が仕様と違えば、パス付きで検証エラーにする', function (): void {
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'total_count'], 'many');

    expect(fn (): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);

    try {
        responseMapper()->itemList($payload);
    } catch (ResponseValidationException $exception) {
        expect($exception->targetClass)->toBe(ItemListResponse::class)
            ->and($exception->errors)->toBe([[
                'path' => 'result.total_count',
                'message' => "Value 'many' is not a valid integer.",
            ]]);
    }
});

test('数値を表す文字列でも int には暗黙変換しない', function (): void {
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'total_count'], '10');

    expect(fn (): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

test('必須項目が欠けていれば検証エラーにする', function (): void {
    // title と URL は欠けることがあるので、例には使えない。content_id は全商品にあった。
    $payload = Fixture::decodedWithout('item-list', ['result', 'items', 0, 'content_id']);

    expect(fn (): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

test('未知のサイトコードは検証エラーにする', function (): void {
    $payload = Fixture::decodedWith('floor-list', ['result', 'site', 0, 'code'], 'NEWSITE');

    expect(fn (): FloorListResponse => responseMapper()->floorList($payload))
        ->toThrow(ResponseValidationException::class);
});
