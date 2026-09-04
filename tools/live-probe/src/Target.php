<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use Closure;
use DmmApiClient\Request\Request;

/**
 * 「同じ条件でページ位置だけを変えて叩く」ひとまとまりの取得対象。
 *
 * 先頭ページを取ってから総件数を見て、中間・末尾のページ位置を決めるため、
 * リクエストは offset を受け取って組み立てる形で保持する。
 */
final readonly class Target
{
    /**
     * @param string                 $group         出力先のサブディレクトリ名（例: ItemList）
     * @param string                 $endpoint      API のエンドポイントパス（例: /ItemList）
     * @param class-string           $responseClass 検証に使う DTO
     * @param string                 $key           ファイル名の先頭に付ける、対象を表す文字列
     * @param string|null            $sort          sort パラメータの値。持たない API では null
     * @param int|null               $hits          hits パラメータの値。持たない API では null
     * @param int                    $offsetMax     offset の上限
     * @param array<string, string>  $context       manifest に残す内訳
     * @param Closure(int): Request  $build         offset からリクエストを組み立てる
     */
    public function __construct(
        public string $group,
        public string $endpoint,
        public string $responseClass,
        public string $key,
        public ?string $sort,
        public ?int $hits,
        public int $offsetMax,
        public array $context,
        private Closure $build,
    ) {
    }

    /**
     * ページングを持たない（1 回叩いて終わる）対象か。
     */
    public function isSingle(): bool
    {
        return $this->hits === null;
    }

    public function request(int $offset): Request
    {
        return ($this->build)($offset);
    }

    /**
     * 条件がそのまま読み取れるファイル名。
     *
     * 例: `FANZA__digital__videoa-43__sort-date__hits-100__offset-000001.json`
     */
    public function fileName(int $offset): string
    {
        $parts = [$this->key];

        if ($this->sort !== null) {
            $parts[] = 'sort-' . self::sanitize($this->sort);
        }

        if ($this->hits !== null) {
            $parts[] = 'hits-' . $this->hits;
            $parts[] = sprintf('offset-%06d', $offset);
        }

        return implode('__', $parts) . '.json';
    }

    /**
     * ファイル名に使えない文字を落とす。
     */
    public static function sanitize(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? $value;
    }
}
