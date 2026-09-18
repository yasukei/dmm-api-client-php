<?php

declare(strict_types=1);

use DmmApiClient\Api\Exception\InvalidArgumentException;
use DmmApiClient\Api\Request\Credentials;

test('クエリパラメータを返す', function (): void {
    $credentials = new Credentials('MY_API_ID', 'myaffiliateid-999');

    expect($credentials->toQueryParameters())->toBe([
        'api_id' => 'MY_API_ID',
        'affiliate_id' => 'myaffiliateid-999',
    ]);
});

scenario('アフィリエイト ID の形式は検証しない', function (string $affiliateId): void {
    // 受け付ける形式は API 側の都合で決まる。合わない値は API がエラーを返すので、そちらに任せる。
    $credentials = new Credentials('MY_API_ID', $affiliateId);

    expect($credentials->affiliateId)->toBe($affiliateId)
        ->and($credentials->toQueryParameters())
        ->toBe(['api_id' => 'MY_API_ID', 'affiliate_id' => $affiliateId]);
})->with([
    'myaffiliateid-999',
    'myaffiliateid-001',
    'myaffiliateid',
    'myaffiliateid-9990',
    'myaffiliateid-999x',
    '',
]);

test('api_id が空なら拒否する', function (): void {
    expect(fn(): Credentials => new Credentials('', 'myaffiliateid-999'))
        ->toThrow(InvalidArgumentException::class, 'api_id must not be empty.');
});
