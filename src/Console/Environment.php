<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\Exception\InvalidArgumentException;
use DmmApiClient\Api\Request\Credentials;

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
    ) {}

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
     * `--env-file` の指定に従って読み込む。
     *
     * 指定があればそのファイルを読み、無ければエラーにする。指定が無ければカレント
     * ディレクトリの `.env` を、あれば読む。置いていないことは普通なので、無くても通す。
     *
     * @throws UsageException 明示的に指定されたファイルが読めない場合
     */
    public static function loadFor(?string $path): self
    {
        if ($path !== null) {
            return self::load($path, required: true);
        }

        $workingDirectory = getcwd();

        return self::load($workingDirectory === false ? null : $workingDirectory . '/.env');
    }

    /**
     * 認証情報を環境変数か `.env` から読み出す。
     *
     * コマンドライン引数からは受け取らない。引数は ps などから他のユーザーにも見え、
     * シェルの履歴にも残るため、認証情報の渡し方として適さない。
     *
     * @throws UsageException           認証情報が揃わない場合
     * @throws InvalidArgumentException 認証情報が Credentials の受け付けない値だった場合
     */
    public function credentials(): Credentials
    {
        $apiId = $this->get('DMM_API_ID');
        $affiliateId = $this->get('DMM_AFFILIATE_ID');

        $missing = [];

        if ($apiId === null) {
            $missing[] = 'DMM_API_ID';
        }

        if ($affiliateId === null) {
            $missing[] = 'DMM_AFFILIATE_ID';
        }

        if ($apiId === null || $affiliateId === null) {
            throw new UsageException(sprintf(
                'Missing credentials: %s. Set them as environment variables, or put them in a .env file.',
                implode(', ', $missing),
            ));
        }

        return new Credentials($apiId, $affiliateId);
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

        $lines = preg_split('/\R/', $contents);

        foreach ($lines !== false ? $lines : [] as $line) {
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
