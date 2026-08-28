<?php

declare(strict_types=1);

use DmmApiClient\Exception\InvalidArgumentException;
use DmmApiClient\Request\Credentials;

test('クエリパラメータを返す', function (): void {
    $credentials = new Credentials('MY_API_ID', 'myaffiliateid-999');

    expect($credentials->toQueryParameters())->toBe([
        'api_id' => 'MY_API_ID',
        'affiliate_id' => 'myaffiliateid-999',
    ]);
});

scenario('末尾が 990〜999 のアフィリエイト ID を受け付ける', function (string $affiliateId): void {
    expect(new Credentials('MY_API_ID', $affiliateId))->toBeInstanceOf(Credentials::class);
})->with(['myaffiliateid-990', 'myaffiliateid-995', 'myaffiliateid-999', 'a-b-c-991']);

scenario('末尾が 990〜999 でないアフィリエイト ID を拒否する', function (string $affiliateId): void {
    expect(fn (): Credentials => new Credentials('MY_API_ID', $affiliateId))
        ->toThrow(InvalidArgumentException::class, 'affiliate_id must end with 990-999');
})->with(['myaffiliateid-001', 'myaffiliateid', 'myaffiliateid-99', 'myaffiliateid-9990', 'myaffiliateid-999x', '']);

test('api_id が空なら拒否する', function (): void {
    expect(fn (): Credentials => new Credentials('', 'myaffiliateid-999'))
        ->toThrow(InvalidArgumentException::class, 'api_id must not be empty.');
});
