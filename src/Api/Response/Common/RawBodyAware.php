<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\Common;

use JsonException;
use LogicException;

/**
 * レスポンス DTO に、受け取ったままの生ボディを持たせる。
 *
 * DTO は知らないキーを落とすため、DMM が実際に何を返しているかを確かめたい場合や、
 * レスポンスをそのまま保存したい場合は生ボディを使う。
 *
 * ```php
 * $response = $client->floorList();
 * $raw = $response->body();
 * ```
 *
 * 生ボディを添えるのは {@see \DmmApiClient\Api\DmmApiClient} で、マッピングに成功した直後に
 * 1 度だけ行う。クライアント側に「直前の呼び出し」の状態を持たないため、
 * 呼び出しを並行させても取り違えない。
 */
trait RawBodyAware
{
    // readonly クラスが使う trait のプロパティは、trait 側でも readonly でなければならない。
    private readonly string $body;

    /**
     * 生ボディを添える。
     *
     * @internal {@see \DmmApiClient\Api\DmmApiClient} がマッピング直後に 1 度だけ呼ぶ。利用者は呼ばない。
     */
    public function attachRawBody(string $body): void
    {
        $this->body = $body;
    }

    /**
     * 受け取ったままの生ボディ。
     *
     * @throws LogicException {@see \DmmApiClient\Api\DmmApiClient} を経由せずに作った DTO の場合
     */
    public function body(): string
    {
        // 未初期化の readonly プロパティに isset() を使っても例外にはならず、false が返る。
        if (! isset($this->body)) {
            throw new LogicException(
                static::class . ' was constructed without a raw body attached (bypassed DmmApiClient).',
            );
        }

        return $this->body;
    }

    /**
     * 生ボディをデコードしたもの。DTO が知らないキーも残る。
     *
     * @throws LogicException {@see \DmmApiClient\Api\DmmApiClient} を経由せずに作った DTO の場合
     * @throws JsonException  生ボディが JSON として読めなかった場合
     */
    public function json(): mixed
    {
        return json_decode($this->body(), true, 512, JSON_THROW_ON_ERROR);
    }
}
