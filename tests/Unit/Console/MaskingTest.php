<?php

declare(strict_types=1);

use DmmApiClient\Console\Application;
use DmmApiClient\CredentialMasker;
use Tests\Support\CapturingOutput;
use Tests\Support\Fixture;
use Tests\Support\StubHttpClient;

/**
 * @param list<string> $arguments
 *
 * @return array{code: int, stdout: string, stderr: string}
 */
function runMasked(array $arguments, ?StubHttpClient $http = null): array
{
    $captured = new CapturingOutput();
    $http ??= StubHttpClient::respondingWithFixture('item-list');
    $code = (new Application($http, $captured->output))->run(['dmm', 'item-list', '--site=FANZA', ...$arguments]);

    return ['code' => $code, 'stdout' => $captured->stdout(), 'stderr' => $captured->stderr()];
}

beforeEach(function (): void {
    // fixture の中に現れる値と同じものを設定し、伏せ字になることを確かめられるようにする。
    putenv('DMM_API_ID=MY_API_ID');
    putenv('DMM_AFFILIATE_ID=myaffiliateid-999');
});

afterEach(function (): void {
    putenv('DMM_API_ID');
    putenv('DMM_AFFILIATE_ID');
});

test('既定でレスポンス中の認証情報を伏せ字にする', function (): void {
    $result = runMasked([]);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->not->toContain('MY_API_ID')
        ->and($result['stdout'])->not->toContain('myaffiliateid-999')
        ->and($result['stdout'])->toContain('"affiliate_id": "***"');
});

test('affiliateURL に埋め込まれた値も伏せ字にする', function (): void {
    // エコーバックだけでなく、アフィリエイトリンクの中にも af_id として入っている。
    expect(Fixture::json('item-list'))->toContain('af_id=myaffiliateid-999');

    $stdout = runMasked([])['stdout'];

    expect($stdout)->toContain('af_id=***');
    expect($stdout)->not->toContain('af_id=myaffiliateid-999');
});

test('既定で dry-run の URI も伏せ字にする', function (): void {
    $result = runMasked(['--dry-run']);

    expect(trim($result['stdout']))
        ->toBe('https://api.dmm.com/affiliate/v3/ItemList?api_id=***&affiliate_id=***&site=FANZA&output=json');
});

test('--raw でも伏せ字にする', function (): void {
    // --raw は整形しないという意味であって、伏せ字を外す意味ではない。
    $result = runMasked(['--raw']);

    expect($result['stdout'])->not->toContain('myaffiliateid-999')
        ->and($result['stdout'])->toContain('***');
});

test('--no-mask なら実際の値をそのまま出す', function (): void {
    $result = runMasked(['--no-mask']);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stdout'])->toContain('"affiliate_id": "myaffiliateid-999"')
        ->and($result['stdout'])->toContain('af_id=myaffiliateid-999')
        ->and($result['stdout'])->not->toContain('***');
});

test('--no-mask なら dry-run の URI もそのまま出す', function (): void {
    expect(trim(runMasked(['--no-mask', '--dry-run'])['stdout']))
        ->toBe('https://api.dmm.com/affiliate/v3/ItemList'
            . '?api_id=MY_API_ID&affiliate_id=myaffiliateid-999&site=FANZA&output=json');
});

test('伏せ字にしてもレスポンスの検証は通る', function (): void {
    // 検証は伏せ字にする前の実際のレスポンスに対して行う。
    $result = runMasked([]);

    expect($result['code'])->toBe(Application::EXIT_SUCCESS)
        ->and($result['stderr'])->toBe('');
});

test('伏せ字にした出力も、そのまま検証にかけられる', function (): void {
    // 収集したレスポンスをテストデータとして使えるよう、マスク後も構造が壊れないこと。
    $masked = runMasked([])['stdout'];

    $response = responseMapper()->itemList(json_decode($masked, true));

    expect($response->result->items[0]->title)->toBe('サンプル動画作品')
        ->and($response->result->items[0]->affiliateUrl)->toContain('af_id=***');
});

/**
 * 型だけが合わないレスポンス。検証エラーのメッセージは合わなかった値をそのまま引用するので、
 * その値がアフィリエイト ID を含んでいると、伏せ字にしない限り標準エラー出力に現れる。
 */
function invalidResponseCarryingCredentials(): StubHttpClient
{
    $payload = Fixture::decodedWith('item-list', ['result', 'items', 0, 'affiliateURL'], [
        'af_id=myaffiliateid-999',
    ]);

    return StubHttpClient::respondingWith(200, (string) json_encode($payload));
}

test('レスポンスの検証エラーに現れる認証情報も伏せ字にする', function (): void {
    $result = runMasked([], invalidResponseCarryingCredentials());

    expect($result['code'])->toBe(Application::EXIT_FAILURE)
        ->and($result['stderr'])->toContain('Response did not match')
        ->and($result['stderr'])->toContain(CredentialMasker::MASK)
        ->and($result['stderr'])->not->toContain('myaffiliateid-999');
});

test('--no-mask なら検証エラーにも実際の値がそのまま出る', function (): void {
    // 上のテストが伏せ字を確かめられていること自体の裏取り。
    $result = runMasked(['--no-mask'], invalidResponseCarryingCredentials());

    expect($result['code'])->toBe(Application::EXIT_FAILURE)
        ->and($result['stderr'])->toContain('myaffiliateid-999');
});

test('エラー時のボディも伏せ字にする', function (): void {
    $body = (string) json_encode([
        'result' => [
            'status' => 400,
            'message' => 'BAD REQUEST',
            'errors' => ['affiliate_id' => 'myaffiliateid-999 is invalid'],
        ],
    ]);

    $result = runMasked([], StubHttpClient::respondingWith(400, $body));

    expect($result['code'])->toBe(Application::EXIT_FAILURE)
        ->and($result['stdout'])->not->toContain('myaffiliateid-999')
        ->and($result['stdout'])->toContain('*** is invalid')
        ->and($result['stderr'])->not->toContain('myaffiliateid-999');
});
