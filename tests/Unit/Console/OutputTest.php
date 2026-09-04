<?php

declare(strict_types=1);

use DmmApiClient\CredentialMasker;
use Tests\Support\CapturingOutput;

test('masked() を通した Output は write / line / error のすべてを伏せ字にする', function (): void {
    $captured = new CapturingOutput();
    $output = $captured->output->masked(CredentialMasker::forSecrets('MY_API_ID', 'myaffiliateid-999'));

    $output->write('body: MY_API_ID');
    $output->line('uri: af_id=myaffiliateid-999');
    $output->error('error: MY_API_ID / myaffiliateid-999');

    expect($captured->stdout())->toBe('body: ***uri: af_id=***' . PHP_EOL)
        ->and($captured->stderr())->toBe('error: *** / ***' . PHP_EOL);
});

test('masked() を通していない Output はそのまま書き出す', function (): void {
    $captured = new CapturingOutput();

    $captured->output->line('MY_API_ID');
    $captured->output->error('myaffiliateid-999');

    expect($captured->stdout())->toBe('MY_API_ID' . PHP_EOL)
        ->and($captured->stderr())->toBe('myaffiliateid-999' . PHP_EOL);
});

test('masked() はもとの Output を変更しない', function (): void {
    // 伏せ字の設定は新しいインスタンスに載る。もとの Output を共有している側に影響しない。
    $captured = new CapturingOutput();

    $captured->output->masked(CredentialMasker::forSecrets('MY_API_ID'));
    $captured->output->error('MY_API_ID');

    expect($captured->stderr())->toBe('MY_API_ID' . PHP_EOL);
});

test('無効にしたマスカーを渡せば伏せ字にしない', function (): void {
    $captured = new CapturingOutput();

    $captured->output->masked(CredentialMasker::disabled())->error('MY_API_ID');

    expect($captured->stderr())->toBe('MY_API_ID' . PHP_EOL);
});
