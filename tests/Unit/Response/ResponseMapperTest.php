<?php

declare(strict_types=1);

use DmmApiClient\Api\Exception\ResponseValidationException;
use DmmApiClient\Api\MonoStock;
use DmmApiClient\Api\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Api\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Api\Response\Error\ErrorResponse;
use DmmApiClient\Api\Response\FloorList\FloorListResponse;
use DmmApiClient\Api\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Api\Response\ItemList\Campaign;
use DmmApiClient\Api\Response\ItemList\Directory;
use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\Api\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Api\Response\ResponseMapper;
use DmmApiClient\Api\Response\SeriesSearch\SeriesSearchResponse;
use Tests\Support\Fixture;

/**
 * 通販（mono）のフロアが返す商品 1 件。
 *
 * フィクスチャの商品は配信（digital）のもので、通販のフロアだけが返す項目を持たない。
 *
 * @param array<string, mixed> $overrides 差し替える項目
 *
 * @return array<string, mixed>
 */
function monoItem(array $overrides = []): array
{
    return array_merge([
        'service_code' => 'mono',
        'service_name' => '通販',
        'floor_code' => 'dvd',
        'floor_name' => 'DVD',
        'category_name' => 'DVD',
        'content_id' => 'n_709sample01',
        'title' => 'サンプル通販商品',
        'URL' => 'https://www.dmm.co.jp/mono/dvd/-/detail/=/cid=n_709sample01/',
        'affiliateURL' => 'https://al.dmm.co.jp/?lurl=example&af_id=myaffiliateid-999',
    ], $overrides);
}

/**
 * キャンペーン 1 件を、比較しやすい配列に均す。
 *
 * @return array{string, string, string}
 */
function campaignToArray(Campaign $campaign): array
{
    return [$campaign->dateBegin, $campaign->dateEnd, $campaign->title];
}

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

scenario('在庫状況を MonoStock に変換する', function (string $value, MonoStock $expected): void {
    // stock を返すのは通販（mono）のフロアだけ。そこでは全商品が必ず持つ。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem(['stock' => $value])]);

    expect(responseMapper()->itemList($payload)->result->items[0]->stock)->toBe($expected);
})->with([
    'stock' => ['stock', MonoStock::Stock],
    'reserve' => ['reserve', MonoStock::Reserve],
    'reserve_empty' => ['reserve_empty', MonoStock::ReserveEmpty],
    'empty' => ['empty', MonoStock::Empty],
    'order' => ['order', MonoStock::Order],
]);

test('未知の在庫状況は検証エラーにする', function (): void {
    // 知らない値が来ればページ全体が例外になる。増えたことを見逃さないための代償。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem(['stock' => 'sold_out'])]);

    expect(fn(): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

test('商品階層を上位から下位の順にマッピングする', function (): void {
    // 実データでは 1〜5 段。空の配列で返ることはない。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem(['directory' => [
        ['id' => 102, 'name' => 'DVD'],
        ['id' => 123, 'name' => 'イメージビデオ'],
        ['id' => 343, 'name' => '女性アイドル・グラビア'],
    ]])]);

    $directory = responseMapper()->itemList($payload)->result->items[0]->directory;

    expect($directory)->not->toBeNull()
        ->and(array_map(static fn(Directory $d): array => [$d->id, $d->name], $directory ?? []))
        ->toBe([[102, 'DVD'], [123, 'イメージビデオ'], [343, '女性アイドル・グラビア']]);
});

test('商品階層を返さない商品では null になる', function (): void {
    // 実データに空配列は無い。返らないフロアと、空の商品階層とを取り違えずに済む。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem()]);

    expect(responseMapper()->itemList($payload)->result->items[0]->directory)->toBeNull();
});

test('商品階層の ID が文字列なら検証エラーにする', function (): void {
    // iteminfo の ID とは違い、実データはすべて数値。文字列を許すのは仕様との差異を見逃すことになる。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem([
        'directory' => [['id' => '102', 'name' => 'DVD']],
    ])]);

    expect(fn(): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

scenario('JAN コードを API が返す文字列のまま保持する', function (string $jancode): void {
    // 先頭が 0 の値も、13 桁でない値も実データにある。形式は検証しない。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem(['jancode' => $jancode])]);

    expect(responseMapper()->itemList($payload)->result->items[0]->jancode)->toBe($jancode);
})->with([
    '13 桁' => ['4997766612850'],
    '先頭が 0' => ['0000049376197'],
    '13 桁でない' => ['9009751388'],
]);

test('JAN コードを返さない商品では null になる', function (): void {
    // 通販でも本・コミックのフロアは返さず、他のフロアでも 3 割ほどの商品が持たない。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem()]);

    expect(responseMapper()->itemList($payload)->result->items[0]->jancode)->toBeNull();
});

test('JAN コードが数値で返れば検証エラーにする', function (): void {
    // 実データは常に文字列。数値を受け入れると、先頭が 0 の値を取り違える余地が生まれる。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem(['jancode' => 4997766612850])]);

    expect(fn(): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

scenario('ISBN を API が返す文字列のまま保持する', function (string $isbn): void {
    // 13 桁と 10 桁があり、10 桁はチェックディジットが X になることがある。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem(['isbn' => $isbn])]);

    expect(responseMapper()->itemList($payload)->result->items[0]->isbn)->toBe($isbn);
})->with([
    'ISBN-13' => ['9784847083761'],
    'ISBN-10' => ['4894235137'],
    'チェックディジットが X' => ['477690151X'],
]);

test('ISBN を返さない商品では null になる', function (): void {
    // 返すのは本・コミックのフロアだけ。そこでも 1〜2 割の商品が持たない。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem()]);

    expect(responseMapper()->itemList($payload)->result->items[0]->isbn)->toBeNull();
});

test('CD の種別をマッピングする', function (): void {
    // CD のフロアは kind ひとつだけを持つオブジェクトを返す。
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem([
        'cdinfo' => ['kind' => 'アルバム'],
    ])]);

    expect(responseMapper()->itemList($payload)->result->items[0]->cdinfo?->kind)->toBe('アルバム');
});

test('CD 以外のフロアの商品では cdinfo が null になる', function (): void {
    $payload = Fixture::decodedWith('item-list', ['result', 'items'], [monoItem()]);

    expect(responseMapper()->itemList($payload)->result->items[0]->cdinfo)->toBeNull();
});

test('キャンペーンの日時を API が返す文字列のまま保持する', function (): void {
    // 動画のフロアの書式。日時に見えるが、DateTimeImmutable には変換しない。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'campaign'], [[
        'date_begin' => '2026-09-04 10:10:00',
        'date_end' => '2026-09-07 09:59:59',
        'title' => '50%OFF',
    ]]);

    $campaign = responseMapper()->itemList($payload)->result->items[0]->campaign;

    expect($campaign)->not->toBeNull()
        ->and(array_map(campaignToArray(...), $campaign ?? []))
        ->toBe([['2026-09-04 10:10:00', '2026-09-07 09:59:59', '50%OFF']]);
});

test('同人のフロアの書式のキャンペーンもマッピングできる', function (): void {
    // 開始日時は ISO 8601、終了日時は常に空文字。動画のフロアとは書式が揃っていない。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'campaign'], [[
        'date_begin' => '2026-09-04T00:00:00Z',
        'date_end' => '',
        'title' => '95%OFF',
    ]]);

    $campaign = responseMapper()->itemList($payload)->result->items[0]->campaign;

    expect($campaign)->not->toBeNull()
        ->and(array_map(campaignToArray(...), $campaign ?? []))
        ->toBe([['2026-09-04T00:00:00Z', '', '95%OFF']]);
});

test('キャンペーンの無い商品では null になる', function (): void {
    expect(responseMapper()->itemList(Fixture::decoded('item-list'))->result->items[0]->campaign)->toBeNull();
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

test('ジャンルの大分類をマッピングする', function (): void {
    // genre にある値と同じものが genre_category にも入る。付いている商品は一部だけ。
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'iteminfo', 'genre_category'], [
        ['id' => 6014, 'name' => 'イメージビデオ'],
    ]);

    $itemInfo = responseMapper()->itemList($payload)->result->items[0]->iteminfo;

    expect($itemInfo?->genreCategory)->toHaveCount(1)
        ->and($itemInfo?->genreCategory[0]->id)->toBe(6014)
        ->and($itemInfo?->genreCategory[0]->name)->toBe('イメージビデオ');
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
        ->and($item->iteminfo)->toBeNull()
        ->and($item->stock)->toBeNull()
        ->and($item->directory)->toBeNull()
        ->and($item->jancode)->toBeNull()
        ->and($item->isbn)->toBeNull()
        ->and($item->cdinfo)->toBeNull()
        ->and($item->campaign)->toBeNull();
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

    expect(fn(): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

test('フロア構成のレスポンスをマッピングする', function (): void {
    $response = responseMapper()->floorList(Fixture::decoded('floor-list'));

    expect($response)->toBeInstanceOf(FloorListResponse::class)
        ->and($response->result->site)->toHaveCount(2)
        ->and($response->result->site[0]->code)->toBe('DMM.com')
        ->and($response->result->site[0]->service)->toHaveCount(2)
        ->and($response->result->site[0]->service[0]->code)->toBe('digital')
        ->and($response->result->site[0]->service[0]->floor)->toHaveCount(2)
        ->and($response->result->site[0]->service[0]->floor[0]->id)->toBe('40')
        ->and($response->result->site[0]->service[0]->floor[0]->code)->toBe('videoc')
        ->and($response->result->site[1]->code)->toBe('FANZA');
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
        ->and($response->result->siteCode)->toBe('FANZA')
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

test('メーカーの別名をマッピングする', function (): void {
    // 別名を持つメーカーはごく少なく、値は読み仮名とは限らない。
    $payload = Fixture::decodedWith('maker-search', ['result', 'maker', 0, 'another_name'], 'hmp/エイチエムピー');

    $maker = responseMapper()->makerSearch($payload)->result->maker[0];

    expect($maker->anotherName)->toBe('hmp/エイチエムピー')
        ->and($maker->ruby)->toBe('さんぷるめーかー');
});

test('別名を持たないメーカーもマッピングできる', function (): void {
    $maker = responseMapper()->makerSearch(Fixture::decoded('maker-search'))->result->maker[0];

    expect($maker->anotherName)->toBeNull();
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

test('区切りの無い別名義もマッピングする', function (): void {
    // スラッシュで連ねた値は実データでは 320 件中 82 件。大半は区切りの無い 1 つの名義。
    $payload = Fixture::decodedWith('author-search', ['result', 'author', 0, 'another_name'], 'NOISE');

    expect(responseMapper()->authorSearch($payload)->result->author[0]->anotherName)->toBe('NOISE');
});

test('エラーレスポンスをマッピングする', function (): void {
    $response = responseMapper()->error(Fixture::decoded('error'));

    expect($response)->toBeInstanceOf(ErrorResponse::class)
        ->and($response->result->status)->toBe(400)
        ->and($response->result->message)->toBe('BAD REQUEST')
        ->and($response->result->errors)->toBe(['affiliate_id' => 'Invalid Request Error']);
});

test('エラーレスポンスもリクエストのエコーバックを持つ', function (): void {
    // 成功時と同じく result と並んで返る。何を送った結果のエラーなのかが読み取れる。
    $request = responseMapper()->error(Fixture::decoded('error'))->request;

    expect($request?->parameters)->toHaveKey('api_id', 'MY_API_ID');
});

test('エコーバックの無いエラーレスポンスも受け付ける', function (): void {
    $payload = Fixture::decoded('error');
    unset($payload['request']);

    expect(responseMapper()->error($payload)->request)->toBeNull();
});

test('DMM 側で項目が増えてもマッピングは壊れない', function (): void {
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'brand_new_field'], 'something');
    $payload['brand_new_top_level'] = ['nested' => true];

    expect(responseMapper()->itemList($payload)->result->status)->toBe(200);
});

test('厳密なマッパーは知らない項目を検証エラーにする', function (): void {
    // 既定のマッパーは黙って捨てるので、項目が増えても気づけない。気づきたい場合の入口。
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'brand_new_field'], 'something');

    expect(fn(): object => ResponseMapper::strict()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

test('知らない項目の検証エラーはコードで判別できる', function (): void {
    // 文言ではなくコードで種類を判別できるようにしてある。
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'brand_new_field'], 'something');

    try {
        ResponseMapper::strict()->itemList($payload);
    } catch (ResponseValidationException $exception) {
        expect($exception->errors)->toHaveCount(1)
            ->and($exception->errors[0]['path'])->toBe('result.brand_new_field')
            ->and($exception->errors[0]['code'])->toBe(ResponseValidationException::CODE_UNEXPECTED_KEY);

        return;
    }

    throw new RuntimeException('Expected the strict mapper to reject the unknown key.');
});

test('型の食い違いは知らない項目とは別のコードになる', function (): void {
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'total_count'], 'many');

    try {
        ResponseMapper::strict()->itemList($payload);
    } catch (ResponseValidationException $exception) {
        expect($exception->errors[0]['code'])->not->toBe(ResponseValidationException::CODE_UNEXPECTED_KEY);

        return;
    }

    throw new RuntimeException('Expected the strict mapper to reject the wrong type.');
});

test('型が仕様と違えば、パス付きで検証エラーにする', function (): void {
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'total_count'], 'many');

    expect(fn(): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);

    try {
        responseMapper()->itemList($payload);
    } catch (ResponseValidationException $exception) {
        expect($exception->targetClass)->toBe(ItemListResponse::class)
            ->and($exception->errors)->toBe([[
                'path' => 'result.total_count',
                'message' => "Value 'many' is not a valid integer.",
                'code' => 'invalid_integer',
            ]]);
    }
});

test('数値を表す文字列でも int には暗黙変換しない', function (): void {
    $payload = Fixture::decodedWith('item-list-empty', ['result', 'total_count'], '10');

    expect(fn(): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});

test('必須項目が欠けていれば検証エラーにする', function (): void {
    // title と URL は欠けることがあるので、例には使えない。content_id は全商品にあった。
    $payload = Fixture::decodedWithout('item-list', ['result', 'items', 0, 'content_id']);

    expect(fn(): ItemListResponse => responseMapper()->itemList($payload))
        ->toThrow(ResponseValidationException::class);
});
