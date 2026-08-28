<?php

declare(strict_types=1);

use DmmApiClient\Request\Credentials;
use DmmApiClient\Response\ResponseMapper;
use Pest\PendingCalls\TestCall;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)->in('Unit');

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
