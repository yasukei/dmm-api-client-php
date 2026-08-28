<?php

declare(strict_types=1);

use DmmApiClient\Console\Application;
use Tests\Support\CapturingOutput;
use Tests\Support\Fixture;
use Tests\Support\StubHttpClient;

/**
 * `dmm floor-list` を実行し、終了コードと出力を返す。
 *
 * @param list<string> $arguments
 *
 * @return array{code: int, stdout: string, stderr: string}
 */
function runFloorList(StubHttpClient $http, array $arguments = []): array
{
    $captured = new CapturingOutput();
    $code = (new Application($http, $captured->output))->run(['dmm', 'floor-list', ...$arguments]);

    return ['code' => $code, 'stdout' => $captured->stdout(), 'stderr' => $captured->stderr()];
}

/**
 * 認証情報が読めない状態にする。ローカルの .env が紛れ込まないよう、空のファイルを指す。
 *
 * @return list<string>
 */
function withoutCredentials(): array
{
    $path = tempnam(sys_get_temp_dir(), 'dmm-empty-env-');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary file.');
    }

    return ['--env-file=' . $path];
}

beforeEach(function (): void {
    putenv('DMM_API_ID=MY_API_ID');
    putenv('DMM_AFFILIATE_ID=myaffiliateid-999');
});

afterEach(function (): void {
    putenv('DMM_API_ID');
    putenv('DMM_AFFILIATE_ID');
});

test('レスポンスを整形して標準出力へ書き出す', function (): void {
    $result = runFloorList(StubHttpClient::respondingWithFixture('floor-list'));

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stderr'])->toBe('')
        ->and($result['stdout'])->toContain('"code": "DMM.com"')
        ->and($result['stdout'])->toContain('ビデオ');

    // 整形済みかつ、日本語もスラッシュもエスケープされていないこと。
    expect(json_decode($result['stdout'], true))->toBeArray()
        ->and($result['stdout'])->not->toContain('\u30d3')
        ->and($result['stdout'])->not->toContain('\/');
});

test('--raw は受け取ったままのボディを出す', function (): void {
    // 既定では認証情報が伏せ字になるため、バイト単位の一致を見るには --no-mask が要る。
    $result = runFloorList(
        StubHttpClient::respondingWithFixture('floor-list'),
        ['--raw', '--no-mask'],
    );

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toBe(Fixture::json('floor-list'));
});

test('--dry-run は送信せずに URI だけを出す', function (): void {
    $http = StubHttpClient::respondingWithFixture('floor-list');
    $result = runFloorList($http, ['--dry-run', '--no-mask']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($http->requests)->toBe([])
        ->and(trim($result['stdout']))->toBe(
            'https://api.dmm.com/affiliate/v3/FloorList'
            . '?api_id=MY_API_ID&affiliate_id=myaffiliateid-999&output=json',
        );
});

test('Accept ヘッダ付きの GET を送る', function (): void {
    $http = StubHttpClient::respondingWithFixture('floor-list');
    runFloorList($http);

    expect($http->lastRequest()->getMethod())->toBe('GET')
        ->and($http->lastRequest()->getHeaderLine('Accept'))->toBe('application/json');
});

test('仕様と食い違うレスポンスは、本文を出したうえで失敗として扱う', function (): void {
    $payload = Fixture::decodedWith('floor-list', ['result', 'site', 0, 'code'], 'NEWSITE');
    $http = StubHttpClient::respondingWith(200, (string) json_encode($payload));

    $result = runFloorList($http);

    expect($result['code'])->toBe(Application::EXIT_FAILURE)
        ->and($result['stdout'])->toContain('NEWSITE')
        ->and($result['stderr'])->toContain('FloorListResponse')
        ->and($result['stderr'])->toContain('result.site.0.code');
});

test('--no-validate-response なら食い違いがあっても成功にする', function (): void {
    $payload = Fixture::decodedWith('floor-list', ['result', 'site', 0, 'code'], 'NEWSITE');
    $http = StubHttpClient::respondingWith(200, (string) json_encode($payload));

    $result = runFloorList($http, ['--no-validate-response']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stderr'])->toBe('');
});

test('API がエラーを返したら、エラー本文を出したうえで失敗にする', function (): void {
    $result = runFloorList(StubHttpClient::respondingWithFixture('error', 400));

    expect($result['code'])->toBe(Application::EXIT_FAILURE)
        ->and($result['stdout'])->toContain('BAD REQUEST')
        ->and($result['stderr'])->toContain('DMM API returned 400 BAD REQUEST');
});

test('通信に失敗したら失敗にする', function (): void {
    $result = runFloorList(StubHttpClient::failingWith('Could not resolve host'));

    expect($result['code'])->toBe(Application::EXIT_FAILURE)
        ->and($result['stdout'])->toBe('')
        ->and($result['stderr'])->toContain('Could not resolve host');
});

test('JSON でないボディは、そのまま出したうえで失敗にする', function (): void {
    $result = runFloorList(StubHttpClient::respondingWith(200, 'not json'));

    expect($result['code'])->toBe(Application::EXIT_FAILURE)
        ->and($result['stdout'])->toContain('not json')
        ->and($result['stderr'])->toContain('Could not pretty-print');
});

test('認証情報が無ければ使い方の誤りとして扱う', function (): void {
    putenv('DMM_API_ID');
    putenv('DMM_AFFILIATE_ID');

    $result = runFloorList(StubHttpClient::respondingWithFixture('floor-list'), withoutCredentials());

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('DMM_API_ID')
        ->and($result['stderr'])->toContain('DMM_AFFILIATE_ID');
});

test('認証情報を .env から読み込む', function (): void {
    putenv('DMM_API_ID');
    putenv('DMM_AFFILIATE_ID');

    $path = tempnam(sys_get_temp_dir(), 'dmm-env-');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary file.');
    }

    file_put_contents($path, "DMM_API_ID=from_file\nDMM_AFFILIATE_ID=fromfile-991\n");

    $result = runFloorList(
        StubHttpClient::respondingWithFixture('floor-list'),
        ['--env-file=' . $path, '--dry-run', '--no-mask'],
    );

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('api_id=from_file')
        ->and($result['stdout'])->toContain('affiliate_id=fromfile-991');
});

test('環境変数が .env より優先される', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'dmm-env-');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary file.');
    }

    file_put_contents($path, "DMM_API_ID=from_file\nDMM_AFFILIATE_ID=fromfile-991\n");

    $result = runFloorList(
        StubHttpClient::respondingWithFixture('floor-list'),
        ['--env-file=' . $path, '--dry-run', '--no-mask'],
    );

    expect($result['stdout'])->toContain('api_id=MY_API_ID');
});

test('--no-validate-request でもリクエストの組み立て方は変わらない', function (): void {
    // /FloorList は固有のパラメータを持たないため、検証を飛ばしても送信内容は同じになる。
    $result = runFloorList(
        StubHttpClient::respondingWithFixture('floor-list'),
        ['--no-validate-request', '--dry-run', '--no-mask'],
    );

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and(trim($result['stdout']))->toBe(
            'https://api.dmm.com/affiliate/v3/FloorList'
            . '?api_id=MY_API_ID&affiliate_id=myaffiliateid-999&output=json',
        );
});

test('アフィリエイト ID の形式が不正なら使い方の誤りとして扱う', function (): void {
    putenv('DMM_AFFILIATE_ID=bad-123');

    $result = runFloorList(StubHttpClient::respondingWithFixture('floor-list'));

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('affiliate_id must end with 990-999');
});

test('未定義のオプションは使い方の誤りとして扱う', function (): void {
    $result = runFloorList(
        StubHttpClient::respondingWithFixture('floor-list'),
        ['--typo'],
    );

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Unknown option "--typo"');
});

test('--help は使い方を出して終わる', function (): void {
    $http = StubHttpClient::respondingWithFixture('floor-list');
    $result = runFloorList($http, ['--help']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($http->requests)->toBe([])
        ->and($result['stdout'])->toContain('dmm floor-list [options]')
        ->and($result['stdout'])->toContain('--env-file=PATH')
        ->and($result['stdout'])->not->toContain('--api-id');
});
