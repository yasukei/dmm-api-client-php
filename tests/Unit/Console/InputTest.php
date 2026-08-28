<?php

declare(strict_types=1);

use DmmApiClient\Console\Input;
use DmmApiClient\Console\OptionDefinition;
use DmmApiClient\Exception\UsageException;

/**
 * @return list<OptionDefinition>
 */
function inputDefinitions(): array
{
    return [
        new OptionDefinition('api-id', 'API ID', 'ID'),
        new OptionDefinition('hits', '取得件数', 'N'),
        new OptionDefinition('raw', '整形しない'),
    ];
}

test('引数が無ければ何も指定されていない', function (): void {
    $input = Input::parse([], inputDefinitions());

    expect($input->option('api-id'))->toBeNull()
        ->and($input->flag('raw'))->toBeFalse();
});

test('イコール区切り（--name=value）で値を読む', function (): void {
    $input = Input::parse(['--api-id=abc123'], inputDefinitions());

    expect($input->option('api-id'))->toBe('abc123');
});

test('空白区切り（--name value）で値を読む', function (): void {
    $input = Input::parse(['--api-id', 'abc123'], inputDefinitions());

    expect($input->option('api-id'))->toBe('abc123');
});

test('フラグを読む', function (): void {
    $input = Input::parse(['--raw'], inputDefinitions());

    expect($input->flag('raw'))->toBeTrue();
});

test('値とフラグを混在させられる', function (): void {
    $input = Input::parse(['--raw', '--api-id', 'abc', '--hits=20'], inputDefinitions());

    expect($input->flag('raw'))->toBeTrue()
        ->and($input->option('api-id'))->toBe('abc')
        ->and($input->option('hits'))->toBe('20');
});

test('= を含む値を壊さない', function (): void {
    $input = Input::parse(['--api-id=a=b=c'], inputDefinitions());

    expect($input->option('api-id'))->toBe('a=b=c');
});

test('同じオプションを繰り返した場合は後の指定が勝つ', function (): void {
    $input = Input::parse(['--api-id=first', '--api-id=second'], inputDefinitions());

    expect($input->option('api-id'))->toBe('second');
});

test('未定義のオプションを拒否する', function (): void {
    expect(fn (): Input => Input::parse(['--typo=1'], inputDefinitions()))
        ->toThrow(UsageException::class, 'Unknown option "--typo".');
});

test('値の無いオプションを拒否する', function (): void {
    expect(fn (): Input => Input::parse(['--api-id'], inputDefinitions()))
        ->toThrow(UsageException::class, 'Option "--api-id" requires a value.');
});

test('次がオプションなら値とみなさない', function (): void {
    expect(fn (): Input => Input::parse(['--api-id', '--raw'], inputDefinitions()))
        ->toThrow(UsageException::class, 'Option "--api-id" requires a value.');
});

test('フラグへの値指定を拒否する', function (): void {
    expect(fn (): Input => Input::parse(['--raw=1'], inputDefinitions()))
        ->toThrow(UsageException::class, 'Option "--raw" does not take a value.');
});

test('オプション以外の引数を拒否する', function (): void {
    expect(fn (): Input => Input::parse(['extra'], inputDefinitions()))
        ->toThrow(UsageException::class, 'Unexpected argument "extra".');
});
