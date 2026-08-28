<?php

declare(strict_types=1);

use DmmApiClient\Console\Application;
use Tests\Support\CapturingOutput;
use Tests\Support\StubHttpClient;

/**
 * 1 コマンドを実行し、終了コードと出力を返す。
 *
 * @param list<string> $arguments
 *
 * @return array{code: int, stdout: string, stderr: string}
 */
function runCommand(string $command, array $arguments, ?StubHttpClient $http = null): array
{
    $captured = new CapturingOutput();
    $http ??= StubHttpClient::respondingWith(200, '{}');
    $code = (new Application($http, $captured->output))->run(['dmm', $command, ...$arguments]);

    return ['code' => $code, 'stdout' => $captured->stdout(), 'stderr' => $captured->stderr()];
}

beforeEach(function (): void {
    putenv('DMM_API_ID=MY_API_ID');
    putenv('DMM_AFFILIATE_ID=myaffiliateid-999');
});

afterEach(function (): void {
    putenv('DMM_API_ID');
    putenv('DMM_AFFILIATE_ID');
});

scenario('各コマンドがレスポンスを取得して DTO 検証を通す', function (string $command, string $arguments, string $fixture, string $marker): void {
    $result = runCommand($command, splitArguments($arguments), StubHttpClient::respondingWithFixture($fixture));

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stderr'])->toBe('')
        ->and($result['stdout'])->toContain($marker);
})->with([
    'item-list' => ['item-list', '--site=FANZA', 'item-list', 'サンプル動画作品'],
    'floor-list' => ['floor-list', '', 'floor-list', 'ビデオ'],
    'actress-search' => ['actress-search', '', 'actress-search', 'サンプル女優'],
    'genre-search' => ['genre-search', '--floor-id=43', 'genre-search', 'ハイビジョン'],
    'maker-search' => ['maker-search', '--floor-id=43', 'maker-search', 'サンプルメーカー'],
    'series-search' => ['series-search', '--floor-id=43', 'series-search', 'サンプルシリーズ'],
    'author-search' => ['author-search', '--floor-id=80', 'author-search', 'サンプル作者'],
]);

scenario('各コマンドが正しいエンドポイントを呼ぶ', function (string $command, string $arguments, string $endpoint): void {
    $http = StubHttpClient::respondingWith(200, '{}');
    runCommand($command, [...splitArguments($arguments), '--no-validate-response'], $http);

    expect($http->lastRequest()->getUri()->getPath())->toBe('/affiliate/v3' . $endpoint);
})->with([
    'item-list' => ['item-list', '--site=FANZA', '/ItemList'],
    'floor-list' => ['floor-list', '', '/FloorList'],
    'actress-search' => ['actress-search', '', '/ActressSearch'],
    'genre-search' => ['genre-search', '--floor-id=43', '/GenreSearch'],
    'maker-search' => ['maker-search', '--floor-id=43', '/MakerSearch'],
    'series-search' => ['series-search', '--floor-id=43', '/SeriesSearch'],
    'author-search' => ['author-search', '--floor-id=80', '/AuthorSearch'],
]);

scenario('フロア指定の検索は floor-id を必須にする', function (string $command): void {
    $result = runCommand($command, ['--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Option "--floor-id" is required.');
})->with([['genre-search'], ['maker-search'], ['series-search'], ['author-search']]);

scenario('フロア指定の検索は同じパラメータを受け付ける', function (string $command): void {
    $result = runCommand($command, ['--floor-id=43', '--initial=あ', '--hits=500', '--offset=101', '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS);

    parse_str((string) parse_url(trim($result['stdout']), PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'floor_id' => '43',
        'initial' => 'あ',
        'hits' => '500',
        'offset' => '101',
    ]);
})->with([['genre-search'], ['maker-search'], ['series-search'], ['author-search']]);

scenario('フロア指定の検索は 501 件以上を拒否する', function (string $command): void {
    $result = runCommand($command, ['--floor-id=43', '--hits=501', '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('hits must be between 1 and 500');
})->with([['genre-search'], ['maker-search'], ['series-search'], ['author-search']]);

test('女優検索のオプションをクエリパラメータに変換する', function (): void {
    $result = runCommand('actress-search', [
        '--initial=あ',
        '--actress-id=1078970',
        '--keyword=あさみ',
        '--gte-bust=85',
        '--lte-bust=95',
        '--gte-waist=55',
        '--lte-waist=65',
        '--gte-hip=80',
        '--lte-hip=95',
        '--gte-height=150',
        '--lte-height=175',
        '--gte-birthday=1990-01-01',
        '--lte-birthday=1999-12-31',
        '--sort=-bust',
        '--hits=100',
        '--offset=21',
        '--dry-run',
    ]);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS);

    parse_str((string) parse_url(trim($result['stdout']), PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'initial' => 'あ',
        'actress_id' => '1078970',
        'keyword' => 'あさみ',
        'gte_bust' => '85',
        'lte_bust' => '95',
        'gte_waist' => '55',
        'lte_waist' => '65',
        'gte_hip' => '80',
        'lte_hip' => '95',
        'gte_height' => '150',
        'lte_height' => '175',
        'gte_birthday' => '1990-01-01',
        'lte_birthday' => '1999-12-31',
        'sort' => '-bust',
        'hits' => '100',
        'offset' => '21',
    ]);
});

test('女優検索の offset に上限はない', function (): void {
    expect(runCommand('actress-search', ['--offset=1000000', '--dry-run'])['code'])
        ->toBe(Application::EXIT_SUCCESS);
});
