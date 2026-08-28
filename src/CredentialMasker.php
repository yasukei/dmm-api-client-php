<?php

declare(strict_types=1);

namespace DmmApiClient;

use DmmApiClient\Request\Credentials;

/**
 * 認証情報の値を、テキストから伏せ字に置き換える。
 *
 * API ID とアフィリエイト ID は、リクエストのエコーバックだけでなく
 * `affiliateURL` や `list_url` の中にも埋め込まれて返ってくる。
 * レスポンスをファイルに残したりログに出したりする前に通すことを想定している。
 *
 * 値そのものを探して置き換えるだけなので、JSON の構造は変わらない。
 * マスク後の文字列も、そのまま DTO へマッピングできる。
 *
 * 部分一致で置き換えるため、極端に短い値を渡すと本文の関係のない箇所にも一致する
 * （例: API ID が "id" なら "affiliate_id" の中の "id" も伏せ字になる）。
 * 伏せ残しよりは伏せ過ぎる方が安全なので、この挙動は許容している。
 */
final readonly class CredentialMasker
{
    /** 認証情報を置き換える文字列。 */
    public const string MASK = '***';

    /**
     * @param list<string> $secrets 長い順に並んだ、置き換える対象
     */
    private function __construct(
        private array $secrets,
    ) {
    }

    public static function forCredentials(Credentials $credentials): self
    {
        return self::forSecrets($credentials->apiId, $credentials->affiliateId);
    }

    /**
     * 任意の値を伏せるマスカーを作る。
     *
     * 空文字は無視する。短い値が長い値の一部だった場合に取りこぼさないよう、長い順に置き換える。
     */
    public static function forSecrets(string ...$secrets): self
    {
        $targets = [];

        foreach ($secrets as $secret) {
            if ($secret === '') {
                continue;
            }

            $targets[] = $secret;

            // URL エンコードされた形でも現れうるので、異なる場合はそれも対象にする。
            foreach ([rawurlencode($secret), urlencode($secret)] as $encoded) {
                if ($encoded !== $secret) {
                    $targets[] = $encoded;
                }
            }
        }

        $targets = array_values(array_unique($targets));

        usort($targets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return new self($targets);
    }

    /**
     * 何も置き換えないマスカー。マスクを無効にした場合に使う。
     */
    public static function disabled(): self
    {
        return new self([]);
    }

    public function mask(string $text): string
    {
        if ($this->secrets === []) {
            return $text;
        }

        return str_replace($this->secrets, self::MASK, $text);
    }
}
