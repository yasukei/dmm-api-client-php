<?php

declare(strict_types=1);

use DmmApiClient\CredentialMasker;
use DmmApiClient\Request\Credentials;

test('認証情報の値を伏せ字にする', function (): void {
    $masker = CredentialMasker::forCredentials(new Credentials('MY_API_ID', 'myaffiliateid-999'));

    expect($masker->mask('api_id=MY_API_ID&affiliate_id=myaffiliateid-999'))
        ->toBe('api_id=***&affiliate_id=***');
});

test('同じ値が何度出てきても、すべて伏せ字にする', function (): void {
    $masker = CredentialMasker::forSecrets('secret-999');

    expect($masker->mask('a=secret-999 b=secret-999 c=secret-999'))
        ->toBe('a=*** b=*** c=***');
});

test('URL に埋め込まれた値も伏せ字にする', function (): void {
    $masker = CredentialMasker::forCredentials(new Credentials('MY_API_ID', 'myaffiliateid-999'));

    expect($masker->mask('https://al.dmm.co.jp/?lurl=example&af_id=myaffiliateid-999&ch=api'))
        ->toBe('https://al.dmm.co.jp/?lurl=example&af_id=***&ch=api');
});

test('URL エンコードされた形も伏せ字にする', function (): void {
    $masker = CredentialMasker::forSecrets('secret value/999');

    expect($masker->mask('raw=secret value/999 enc=secret%20value%2F999 plus=secret+value%2F999'))
        ->toBe('raw=*** enc=*** plus=***');
});

test('短い値が長い値の一部でも、長い方を先に伏せる', function (): void {
    $masker = CredentialMasker::forSecrets('abc', 'abc-999');

    expect($masker->mask('id=abc-999'))->toBe('id=***');
});

test('関係のない文字列は変えない', function (): void {
    $masker = CredentialMasker::forCredentials(new Credentials('MY_API_ID', 'myaffiliateid-999'));

    expect($masker->mask('{"title":"サンプル動画作品"}'))->toBe('{"title":"サンプル動画作品"}');
});

test('空の値は伏せ字の対象にしない', function (): void {
    // 空文字を対象にすると、あらゆる位置に一致して文字列が壊れる。
    $masker = CredentialMasker::forSecrets('', 'real-999');

    expect($masker->mask('a=real-999 b=other'))->toBe('a=*** b=other');
});

test('形式を検証していない認証情報でも伏せ字にできる', function (): void {
    $masker = CredentialMasker::forCredentials(Credentials::unchecked('MY_API_ID', 'not-a-valid-affiliate'));

    expect($masker->mask('affiliate_id=not-a-valid-affiliate'))->toBe('affiliate_id=***');
});

test('極端に短い値は、関係のない箇所にも一致する', function (): void {
    // 部分一致で置き換えるため避けられない。伏せ残すよりは伏せ過ぎる方が安全という判断。
    $masker = CredentialMasker::forSecrets('id');

    expect($masker->mask('affiliate_id=x'))->toBe('affiliate_***=x');
});

test('disabled は何も置き換えない', function (): void {
    expect(CredentialMasker::disabled()->mask('api_id=MY_API_ID'))->toBe('api_id=MY_API_ID');
});

test('伏せ字にしても JSON として読める', function (): void {
    $masker = CredentialMasker::forCredentials(new Credentials('MY_API_ID', 'myaffiliateid-999'));
    $json = (string) json_encode(['affiliate_id' => 'myaffiliateid-999', 'title' => 'サンプル']);

    $decoded = json_decode($masker->mask($json), true);

    expect($decoded)->toBe(['affiliate_id' => '***', 'title' => 'サンプル']);
});
