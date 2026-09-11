<?php

declare(strict_types=1);

use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\LiveProbe\Validator;

test('DTO が揃っていれば検証を通る', function (): void {
    $body = file_get_contents(__DIR__ . '/../../Fixtures/item-list.json');

    expect($body)->toBeString()
        ->and((new Validator())->validate(ItemListResponse::class, (string) $body))->toBe([]);
});

test('ボディが JSON でなければ例外にせずエラーとして返す', function (): void {
    expect((new Validator())->validate(ItemListResponse::class, 'not json'))
        ->toBe([['path' => '*json*', 'message' => 'Response body is not a JSON object.']]);
});

scenario('無い DTO を指しても例外にせずエラーとして返す', function (string $responseClass): void {
    /** @var class-string $responseClass */
    $errors = (new Validator())->validate($responseClass, '{"result":{}}');

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['path'])->toBe('*class*')
        ->and($errors[0]['message'])->toContain($responseClass);
})->with([
    // manifest.jsonl に responseClass が無い行を読むと、この形で渡ってくる。
    '名前が空' => [''],
    '消えたクラス' => ['DmmApiClient\\Api\\Response\\Gone\\GoneResponse'],
]);

scenario('無い DTO を指しても知らないキーの検出は例外にしない', function (string $responseClass): void {
    /** @var class-string $responseClass */
    expect((new Validator())->unknownKeys($responseClass, '{"result":{}}'))->toBe([]);
})->with([
    '名前が空' => [''],
    '消えたクラス' => ['DmmApiClient\\Api\\Response\\Gone\\GoneResponse'],
]);
