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
function runApplication(array $arguments): array
{
    $captured = new CapturingOutput();
    $http = StubHttpClient::respondingWith(200, '{}');
    $code = (new Application($http, $captured->output))->run(['dmm', ...$arguments]);

    return ['code' => $code, 'stdout' => $captured->stdout(), 'stderr' => $captured->stderr()];
}

beforeEach(function (): void {
    // 環境変数は .env より優先される。開発環境に置かれた .env を
    // 拾って結果が変わらないよう、テスト用の値を明示的に置く。
    putenv('DMM_API_ID=MY_API_ID');
    putenv('DMM_AFFILIATE_ID=myaffiliateid-999');
});

afterEach(function (): void {
    putenv('DMM_API_ID');
    putenv('DMM_AFFILIATE_ID');
});

test('引数が無ければコマンド一覧を出す', function (): void {
    $result = runApplication([]);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('dmm <command> [options]')
        ->and($result['stdout'])->toContain('floor-list');
});

test('--help でコマンド一覧を出す', function (): void {
    expect(runApplication(['--help'])['stdout'])->toContain('dmm <command> [options]');
});

test('短いオプションは受け付けない', function (): void {
    // このコマンドが持つのは長いオプションだけ。-h も例外にしない。
    $result = runApplication(['-h']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Unknown command "-h"')
        ->and($result['stdout'])->toContain('dmm <command> [options]');
});

test('コマンドの --help は使い方を出す', function (): void {
    $result = runApplication(['item-list', '--help']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('dmm item-list [options]')
        ->and($result['stdout'])->toContain('--site');
});

test('オプションの値として書かれた -h を横取りしない', function (): void {
    // パースの前に引数を走査していると、これがヘルプ表示になってしまう。
    $result = runApplication(['item-list', '--site=FANZA', '--keyword', '-h', '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('keyword=-h')
        ->and($result['stdout'])->not->toContain('dmm item-list [options]');
});

test('コマンドの -h は使い方の誤りとして扱う', function (): void {
    $result = runApplication(['item-list', '-h']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Unexpected argument "-h"')
        ->and($result['stderr'])->toContain('Run "dmm item-list --help" for usage.');
});

test('コマンド一覧に認証情報の渡し方を書く', function (): void {
    $stdout = runApplication([])['stdout'];

    expect($stdout)->toContain('DMM_API_ID')
        ->and($stdout)->toContain('DMM_AFFILIATE_ID')
        ->and($stdout)->toContain('.env');
});

test('知らないコマンドは使い方の誤りとして扱う', function (): void {
    $result = runApplication(['bogus']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Unknown command "bogus"')
        ->and($result['stdout'])->toContain('floor-list');
});
