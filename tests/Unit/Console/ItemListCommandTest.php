<?php

declare(strict_types=1);

use DmmApiClient\Console\Application;
use Tests\Support\CapturingOutput;
use Tests\Support\StubHttpClient;

/**
 * @param list<string> $arguments
 *
 * @return array{code: int, stdout: string, stderr: string}
 */
function runItemList(array $arguments, ?StubHttpClient $http = null): array
{
    $captured = new CapturingOutput();
    $http ??= StubHttpClient::respondingWithFixture('item-list');
    $code = (new Application($http, $captured->output))->run(['dmm-api-client', 'item-list', ...$arguments]);

    return ['code' => $code, 'stdout' => $captured->stdout(), 'stderr' => $captured->stderr()];
}

/**
 * --dry-run で組み立てられた URI のクエリを、デコード済みの配列で返す。
 *
 * @param list<string> $arguments
 *
 * @return array<int|string, array<mixed>|string>
 */
function itemListQuery(array $arguments): array
{
    $result = runItemList([...$arguments, '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS);

    parse_str((string) parse_url(trim($result['stdout']), PHP_URL_QUERY), $query);

    return $query;
}

beforeEach(function (): void {
    putenv('DMM_API_ID=MY_API_ID');
    putenv('DMM_AFFILIATE_ID=myaffiliateid-999');
});

afterEach(function (): void {
    putenv('DMM_API_ID');
    putenv('DMM_AFFILIATE_ID');
});

test('オプションをクエリパラメータに変換する', function (): void {
    $query = itemListQuery([
        '--site=FANZA',
        '--service=digital',
        '--floor=videoa',
        '--keyword=アクション',
        '--cid=mizd00320',
        '--gte-date=2016-04-01',
        '--lte-date=2016-04-30T23:59:59',
        '--mono-stock=stock',
        '--sort=-price',
        '--hits=100',
        '--offset=21',
    ]);

    expect($query)->toMatchArray([
        'site' => 'FANZA',
        'service' => 'digital',
        'floor' => 'videoa',
        'keyword' => 'アクション',
        'cid' => 'mizd00320',
        'gte_date' => '2016-04-01T00:00:00',
        'lte_date' => '2016-04-30T23:59:59',
        'mono_stock' => 'stock',
        'sort' => '-price',
        'hits' => '100',
        'offset' => '21',
        'output' => 'json',
    ]);
});

test('site だけ指定すれば足りる', function (): void {
    expect(itemListQuery(['--site=DMM.com']))->toMatchArray(['site' => 'DMM.com'])
        ->and(array_keys(itemListQuery(['--site=DMM.com'])))
        ->toBe(['api_id', 'affiliate_id', 'site', 'output']);
});

test('article を複数指定できる', function (): void {
    $query = itemListQuery([
        '--site=FANZA',
        '--article=genre',
        '--article-id=6533',
        '--article', 'actress',
        '--article-id', '1078970',
    ]);

    expect($query)->toMatchArray([
        'article' => ['genre', 'actress'],
        'article_id' => ['6533', '1078970'],
    ]);
});

test('article と article-id の数が合わなければ拒否する', function (): void {
    $result = runItemList(['--site=FANZA', '--article=genre', '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('same number of times (1 vs 0)');
});

test('site は必須', function (): void {
    $result = runItemList(['--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Option "--site" is required.');
});

scenario('不正な値を、受け付ける値とともに拒否する', function (string $arguments, string $expected): void {
    $result = runItemList(['--site=FANZA', ...splitArguments($arguments), '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain($expected);
})->with([
    'site' => ['--site=BOGUS', 'Expected one of: DMM.com, FANZA.'],
    'sort' => ['--sort=nope', 'Expected one of: rank, price, -price, date, review, match.'],
    'mono-stock' => ['--mono-stock=nope', 'Expected one of: stock, reserve, reserve_empty, mono.'],
    'article' => ['--article=nope --article-id=1', 'Expected one of: actress, author, genre, series, maker.'],
    'hits が整数でない' => ['--hits=abc', 'Option "--hits" must be an integer'],
    'hits が範囲外' => ['--hits=101', 'hits must be between 1 and 100'],
    'offset が範囲外' => ['--offset=50001', 'offset must be between 1 and 50000'],
    'date が読めない' => ['--gte-date=yesterday', 'must be a date like 2016-04-01'],
    // createFromFormat は範囲外を切り上げるので、放っておくと別の日付として通ってしまう。
    '日が月の末日を超える' => ['--gte-date=2016-04-31', 'must be a date like 2016-04-01'],
    '月が範囲外' => ['--gte-date=2016-13-45', 'must be a date like 2016-04-01'],
    '閏年でない年の 2/29' => ['--gte-date=2015-02-29', 'must be a date like 2016-04-01'],
    '時刻が範囲外' => ['--lte-date=2016-04-01T25:00:00', 'must be a date like 2016-04-01'],
    // 月日を詰めて書いても、埋めた結果が実在しない日付なら弾く。
    'ゼロ埋めしても実在しない' => ['--gte-date=2016-4-31', 'must be a date like 2016-04-01'],
    // 分・秒を詰めた表記は受け付けない。
    '時刻のゼロ埋めがない' => ['--gte-date=2016-04-01T1:2:3', 'must be a date like 2016-04-01'],
]);

scenario('日付は時刻付きでも日付だけでも受け付ける', function (string $value, string $expected): void {
    expect(itemListQuery(['--site=FANZA', '--gte-date=' . $value]))->toMatchArray(['gte_date' => $expected]);
})->with([
    '日付だけ' => ['2016-04-01', '2016-04-01T00:00:00'],
    'T 区切り' => ['2016-04-01T12:34:56', '2016-04-01T12:34:56'],
    '空白区切り' => ['2016-04-01 12:34:56', '2016-04-01T12:34:56'],
    // 実在する日付は、月の末日でも閏日でも通ること。
    '月の末日' => ['2016-04-30', '2016-04-30T00:00:00'],
    '閏日' => ['2016-02-29', '2016-02-29T00:00:00'],
    // 日付は桁を詰めて書かれることがあるので、年・月・日を埋めてから解釈する。
    '月日のゼロ埋めなし' => ['2016-4-1', '2016-04-01T00:00:00'],
    '月だけゼロ埋めなし' => ['2016-4-01', '2016-04-01T00:00:00'],
    '年のゼロ埋めなし' => ['999-1-1', '0999-01-01T00:00:00'],
    'ゼロ埋めなしで時刻付き' => ['2016-4-1T12:34:56', '2016-04-01T12:34:56'],
    'ゼロ埋めなしで空白区切り' => ['2016-4-1 12:34:56', '2016-04-01T12:34:56'],
]);

scenario('--lte-date は、日付だけならその日の終わりとして送る', function (string $value, string $expected): void {
    // 上限を 00:00:00 と読むと、その日に発売・配信された商品がまるごと範囲から外れる。
    expect(itemListQuery(['--site=FANZA', '--lte-date=' . $value]))->toMatchArray(['lte_date' => $expected]);
})->with([
    '日付だけ' => ['2016-04-30', '2016-04-30T23:59:59'],
    '月日のゼロ埋めなし' => ['2016-4-30', '2016-04-30T23:59:59'],
    // 時刻が書かれていれば、埋めずにその時刻を使う。
    'T 区切り' => ['2016-04-30T12:34:56', '2016-04-30T12:34:56'],
    '空白区切り' => ['2016-04-30 12:34:56', '2016-04-30T12:34:56'],
    // 0 時を明示した場合まで日の終わりにしてしまわないこと。
    '0 時を明示' => ['2016-04-30T00:00:00', '2016-04-30T00:00:00'],
]);

test('同じ日付を渡しても、下限は日の始まり・上限は日の終わりになる', function (): void {
    // 埋める時刻はオプションごとに違う。1 日を指定する書き方が、その 1 日全体を指すように。
    expect(itemListQuery(['--site=FANZA', '--gte-date=2016-04-01', '--lte-date=2016-04-01']))
        ->toMatchArray([
            'gte_date' => '2016-04-01T00:00:00',
            'lte_date' => '2016-04-01T23:59:59',
        ]);
});

test('レスポンスを取得して検証する', function (): void {
    $result = runItemList(['--site=FANZA']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('サンプル動画作品')
        ->and($result['stderr'])->toBe('');
});

test('--no-validate-request なら、通常は弾かれる値もそのまま送る', function (): void {
    $result = runItemList([
        '--site=BOGUS',
        '--sort=nonexistent',
        '--hits=9999',
        '--no-validate-request',
        '--dry-run',
    ]);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS);

    parse_str((string) parse_url(trim($result['stdout']), PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'site' => 'BOGUS',
        'sort' => 'nonexistent',
        'hits' => '9999',
    ]);
});

test('--no-validate-request なら必須オプションも要求しない', function (): void {
    $result = runItemList(['--no-validate-request', '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->not->toContain('site=');
});

test('--no-validate-request でも複数指定はそのまま送る', function (): void {
    $result = runItemList([
        '--article=whatever',
        '--article=another',
        '--article-id=1',
        '--no-validate-request',
        '--dry-run',
    ]);

    parse_str((string) parse_url(trim($result['stdout']), PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'article' => ['whatever', 'another'],
        'article_id' => '1',
    ]);
});

test('アフィリエイト ID は形式を問わずそのまま送る', function (): void {
    // 受け付ける形式は API 側の都合で決まるため、コマンド側では検証しない。
    putenv('DMM_AFFILIATE_ID=myaffiliateid-123');

    $result = runItemList(['--site=FANZA', '--dry-run', '--no-mask']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('affiliate_id=myaffiliateid-123');
});
