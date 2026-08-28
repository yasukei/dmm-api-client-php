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

test('引数が無ければコマンド一覧を出す', function (): void {
    $result = runApplication([]);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('dmm <command> [options]')
        ->and($result['stdout'])->toContain('floor-list');
});

scenario('--help と -h でコマンド一覧を出す', function (string $flag): void {
    expect(runApplication([$flag])['stdout'])->toContain('dmm <command> [options]');
})->with([['--help'], ['-h']]);

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
