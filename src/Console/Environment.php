<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Exception\UsageException;

/**
 * 環境変数と `.env` ファイルから設定値を読み出す。
 *
 * 同じ名前が両方にある場合はプロセスの環境変数を優先する。`.env` は
 * 「環境変数が設定されていないときの既定値」として扱う、という一般的な挙動に合わせている。
 *
 * `.env` の書式は最小限のものだけを解釈する。
 *  - `#` で始まる行と空行は無視する
 *  - 先頭の `export ` は無視する
 *  - `KEY=value` 形式。値を `"` か `'` で囲んだ場合は引用符を取り除く
 *  - 引用符で囲まれていない値の後ろにある ` #` 以降はコメントとして捨てる
 *  - 変数展開（`$OTHER`）や複数行の値には対応しない
 */
final readonly class Environment
{
    /**
     * @param array<string, string> $fileValues
     */
    private function __construct(
        private array $fileValues,
    ) {
    }

    /**
     * @param string|null $path     読み込む `.env` のパス
     * @param bool        $required true の場合、ファイルが無ければ例外にする
     *
     * @throws UsageException 明示的に指定されたファイルが読めない場合
     */
    public static function load(?string $path, bool $required = false): self
    {
        if ($path === null || ! is_file($path)) {
            if ($required) {
                throw new UsageException(sprintf('Env file "%s" does not exist.', $path ?? ''));
            }

            return new self([]);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new UsageException(sprintf('Env file "%s" could not be read.', $path));
        }

        return new self(self::parse($contents));
    }

    /**
     * 環境変数を優先し、無ければ `.env` の値を返す。
     */
    public function get(string $name): ?string
    {
        $fromProcess = getenv($name);

        if (is_string($fromProcess) && $fromProcess !== '') {
            return $fromProcess;
        }

        return $this->fileValues[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function parse(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $matches) !== 1) {
                continue;
            }

            $values[$matches[1]] = self::parseValue(trim($matches[2]));
        }

        return $values;
    }

    private static function parseValue(string $value): string
    {
        // 引用符で囲まれている場合は、閉じ引用符までが値。その後ろは捨てる。
        foreach (['"', "'"] as $quote) {
            if (! str_starts_with($value, $quote)) {
                continue;
            }

            $closing = strpos($value, $quote, 1);

            if ($closing !== false) {
                return substr($value, 1, $closing - 1);
            }
        }

        $commentPosition = strpos($value, ' #');

        return $commentPosition === false ? $value : rtrim(substr($value, 0, $commentPosition));
    }
}
