<?php

declare(strict_types=1);

/*
 * LocalServer で DMM API の代わりに動かすルーター。
 *
 * エンドポイントに対応する fixture を返し、対応するものがなければ error の fixture を 400 で返す。
 */

$fixtures = [
    '/ItemList' => 'item-list',
];

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = is_string($requestUri) ? parse_url($requestUri, PHP_URL_PATH) : null;
$fixture = is_string($path) ? $fixtures[$path] ?? null : null;

http_response_code($fixture === null ? 400 : 200);
header('Content-Type: application/json');
readfile(__DIR__ . '/../Fixtures/' . ($fixture ?? 'error') . '.json');
