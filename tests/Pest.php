<?php

declare(strict_types=1);

use DmmApiClient\Api\Request\Credentials;
use DmmApiClient\Api\Response\ResponseMapper;
use DmmApiClient\Console\Application;
use Http\Discovery\ClassDiscovery;
use Pest\PendingCalls\TestCall;
use Tests\Support\CapturingOutput;
use Tests\Support\StubHttpClient;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)->in('Unit', 'Integration');

/*
 * コンソールのテストは、認証情報が環境変数から読める状態で走らせる。
 *
 * 環境変数は .env より優先されるので、開発環境に置かれた .env を拾って結果が変わることはない。
 * 値は fixture の中に現れるものと揃えてあり、伏せ字になることもこのまま確かめられる。
 */
pest()
    ->beforeEach(function (): void {
        putenv('DMM_API_ID=MY_API_ID');
        putenv('DMM_AFFILIATE_ID=myaffiliateid-999');
    })
    ->afterEach(function (): void {
        putenv('DMM_API_ID');
        putenv('DMM_AFFILIATE_ID');
    })
    ->in('Unit/Console');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * データセット付きのテストを定義する。
 *
 * Pest の `test()` は宣言上 `HigherOrderTapProxy|TestCall` を返すが、
 * `HigherOrderTapProxy` には `->with()` の型情報がないため、
 * `test(...)->with(...)` は静的解析で未定義メソッド扱いになる。
 * ここで `TestCall` に絞り込むことで、データセット付きのテストも解析できるようにする。
 */
function scenario(string $description, Closure $closure): TestCall
{
    $call = test($description, $closure);

    if (! $call instanceof TestCall) {
        throw new LogicException('test() did not return a TestCall.');
    }

    return $call;
}

function responseMapper(): ResponseMapper
{
    return new ResponseMapper();
}

function credentials(): Credentials
{
    return new Credentials('MY_API_ID', 'myaffiliateid-999');
}

/**
 * 空白区切りのコマンドライン引数を配列にする。
 *
 * データセットの各行を string で表現できるようにするためのもの。
 * 値に空白を含む引数は扱えない。
 *
 * @return list<string>
 */
function splitArguments(string $arguments): array
{
    return $arguments === '' ? [] : explode(' ', $arguments);
}

/**
 * サブコマンドを 1 つ実行し、終了コードと出力を返す。
 *
 * @param string|null         $command   実行するサブコマンド。null ならコマンドを指定せずに起動する
 * @param list<string>        $arguments
 * @param StubHttpClient|null $http      応答を返すクライアント。null なら空の JSON を返す
 *
 * @return array{code: int, stdout: string, stderr: string}
 */
function runCommand(?string $command, array $arguments = [], ?StubHttpClient $http = null): array
{
    $captured = new CapturingOutput();
    $http ??= StubHttpClient::respondingWith(200, '{}');
    $argv = $command === null ? ['dmm-api-client'] : ['dmm-api-client', $command];
    $code = (new Application($http, $captured->output))->run([...$argv, ...$arguments]);

    return ['code' => $code, 'stdout' => $captured->stdout(), 'stderr' => $captured->stderr()];
}

/**
 * `--dry-run` で組み立てられた URI のクエリを、デコード済みの配列で返す。
 *
 * @param list<string> $arguments `--dry-run` は付けなくてよい
 *
 * @return array<int|string, array<mixed>|string>
 */
function dryRunQuery(string $command, array $arguments): array
{
    $result = runCommand($command, [...$arguments, '--dry-run']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS);

    parse_str((string) parse_url(trim($result['stdout']), PHP_URL_QUERY), $query);

    return $query;
}

/**
 * 実装の自動検出が効かない状態で $callback を実行する。
 *
 * 自動検出はインストール済みのパッケージを探す戦略に任せている。戦略を空にすると、
 * 実装を入れていない環境と同じ状態になる。
 *
 * @template T
 *
 * @param callable(): T $callback
 *
 * @return T
 */
function withoutDiscovery(callable $callback): mixed
{
    $strategies = ClassDiscovery::getStrategies();
    ClassDiscovery::setStrategies([]);

    try {
        return $callback();
    } finally {
        ClassDiscovery::setStrategies(iterator_to_array($strategies));
    }
}
