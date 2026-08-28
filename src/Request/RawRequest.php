<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

/**
 * 与えられたパラメータをそのまま送るリクエスト。
 *
 * 各エンドポイント専用のリクエストクラスと違い、値の検証も型変換も行わない。
 * API が実際にどう応答するかを確かめたい場合や、このライブラリがまだ対応していない
 * パラメータを試したい場合の逃げ道として用意している。
 *
 * 通常は {@see ItemListRequest} などの専用クラスを使う。
 */
final readonly class RawRequest implements Request
{
    /**
     * @param string                             $endpoint   API のエンドポイントパス（例: `/ItemList`）
     * @param array<string, string|list<string>> $parameters クエリに載せるパラメータ
     */
    public function __construct(
        private string $endpoint,
        private array $parameters = [],
    ) {
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toQueryParameters(): array
    {
        return $this->parameters;
    }
}
