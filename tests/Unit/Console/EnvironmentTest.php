<?php

declare(strict_types=1);

use DmmApiClient\Console\Environment;
use DmmApiClient\Exception\UsageException;

/**
 * 一時ファイルに .env を書き出し、そのパスを返す。
 */
function writeEnvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'dmm-env-');

    if ($path === false) {
        throw new RuntimeException('Could not create a temporary file.');
    }

    file_put_contents($path, $contents);

    return $path;
}

afterEach(function (): void {
    putenv('DMM_TEST_VALUE');
});

test('ファイルが無ければ何も読み込まない', function (): void {
    expect(Environment::load('/nonexistent/.env')->get('DMM_TEST_VALUE'))->toBeNull();
});

test('パスを渡さなければ何も読み込まない', function (): void {
    expect(Environment::load(null)->get('DMM_TEST_VALUE'))->toBeNull();
});

test('必須指定でファイルが無ければ例外にする', function (): void {
    expect(fn (): Environment => Environment::load('/nonexistent/.env', required: true))
        ->toThrow(UsageException::class, 'does not exist');
});

test('KEY=value を読む', function (): void {
    $path = writeEnvFile("DMM_TEST_VALUE=plain\n");

    expect(Environment::load($path)->get('DMM_TEST_VALUE'))->toBe('plain');
});

test('コメントと空行を無視する', function (): void {
    $path = writeEnvFile("# comment\n\n   \nDMM_TEST_VALUE=plain\n");

    expect(Environment::load($path)->get('DMM_TEST_VALUE'))->toBe('plain');
});

test('先頭の export を無視する', function (): void {
    $path = writeEnvFile("export DMM_TEST_VALUE=exported\n");

    expect(Environment::load($path)->get('DMM_TEST_VALUE'))->toBe('exported');
});

scenario('引用符を取り除く', function (string $line, string $expected): void {
    expect(Environment::load(writeEnvFile($line))->get('DMM_TEST_VALUE'))->toBe($expected);
})->with([
    'double' => ['DMM_TEST_VALUE="quoted"', 'quoted'],
    'single' => ["DMM_TEST_VALUE='quoted'", 'quoted'],
    '引用符の中の空白' => ['DMM_TEST_VALUE=" spaced "', ' spaced '],
]);

test('行末コメントを取り除く', function (): void {
    $path = writeEnvFile("DMM_TEST_VALUE=plain # trailing comment\n");

    expect(Environment::load($path)->get('DMM_TEST_VALUE'))->toBe('plain');
});

test('引用符の中の # はコメントとみなさない', function (): void {
    $path = writeEnvFile("DMM_TEST_VALUE=\"a # b\"   # trailing comment\n");

    expect(Environment::load($path)->get('DMM_TEST_VALUE'))->toBe('a # b');
});

test('環境変数が .env より優先される', function (): void {
    $path = writeEnvFile("DMM_TEST_VALUE=from_file\n");
    putenv('DMM_TEST_VALUE=from_environment');

    expect(Environment::load($path)->get('DMM_TEST_VALUE'))->toBe('from_environment');
});

test('環境変数が空文字なら .env の値を使う', function (): void {
    $path = writeEnvFile("DMM_TEST_VALUE=from_file\n");
    putenv('DMM_TEST_VALUE=');

    expect(Environment::load($path)->get('DMM_TEST_VALUE'))->toBe('from_file');
});
