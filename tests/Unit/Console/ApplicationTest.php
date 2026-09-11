<?php

declare(strict_types=1);

use DmmApiClient\Console\Application;
use Http\Discovery\ClassDiscovery;
use Tests\Support\CapturingOutput;

/**
 * @param list<string> $arguments
 *
 * @return array{code: int, stdout: string, stderr: string}
 */
function runApplication(array $arguments): array
{
    return runCommand(null, $arguments);
}

test('引数が無ければコマンド一覧を出す', function (): void {
    $result = runApplication([]);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('dmm-api-client <command> [options]')
        ->and($result['stdout'])->toContain('floor-list');
});

test('--help でコマンド一覧を出す', function (): void {
    expect(runApplication(['--help'])['stdout'])->toContain('dmm-api-client <command> [options]');
});

test('短いオプションは受け付けない', function (): void {
    // このコマンドが持つのは長いオプションだけ。-h も例外にしない。
    $result = runApplication(['-h']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Unknown command "-h"')
        ->and($result['stdout'])->toContain('dmm-api-client <command> [options]');
});

test('コマンドの --help は使い方を出す', function (): void {
    $result = runApplication(['item-list', '--help']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('dmm-api-client item-list [options]')
        ->and($result['stdout'])->toContain('--site');
});

test('オプションの値として書かれた -h を横取りしない', function (): void {
    // パースの前に引数を走査していると、これがヘルプ表示になってしまう。
    $result = runApplication(['item-list', '--site=FANZA', '--keyword', '-h', '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('keyword=-h')
        ->and($result['stdout'])->not->toContain('dmm-api-client item-list [options]');
});

test('コマンドの -h は使い方の誤りとして扱う', function (): void {
    $result = runApplication(['item-list', '-h']);

    expect($result['code'])->toBe(Application::EXIT_USAGE)
        ->and($result['stderr'])->toContain('Unexpected argument "-h"')
        ->and($result['stderr'])->toContain('Run "dmm-api-client item-list --help" for usage.');
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

test('PSR-18 の実装が見つからなければ使い方のエラーにする', function (): void {
    // 実装の自動検出は、インストール済みのパッケージを探す戦略に任せている。
    // 戦略を空にすると、実装を入れていない環境と同じ状態になる。
    $strategies = ClassDiscovery::getStrategies();
    ClassDiscovery::setStrategies([]);

    try {
        $captured = new CapturingOutput();
        // 自動検出を通したいので、スタブのクライアントは渡さない。
        $code = (new Application(null, $captured->output))->run(['dmm-api-client', 'floor-list']);
    } finally {
        ClassDiscovery::setStrategies(iterator_to_array($strategies));
    }

    expect($code)->toBe(Application::EXIT_USAGE)
        ->and($captured->stderr())->toContain('No PSR-18 clients found')
        ->and($captured->stderr())->toContain('composer require guzzlehttp/guzzle');
});
