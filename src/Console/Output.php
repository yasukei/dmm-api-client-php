<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\CredentialMasker;

/**
 * 標準出力・標準エラー出力への書き出し。
 *
 * API のレスポンスは標準出力へ、それ以外の診断メッセージは標準エラー出力へ書き出す。
 * パイプでつないだときにレスポンスだけが流れるようにするための分離。
 *
 * 認証情報の伏せ字は {@see self::masked()} で受け取り、書き出す直前に適用する。
 * 呼び出し側が個別に伏せ字にするのではなく出口でまとめて通すことで、
 * 伏せ忘れた経路が残らないようにしている。
 */
final class Output
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private $stdout = STDOUT,
        private $stderr = STDERR,
        private readonly ?CredentialMasker $masker = null,
    ) {
    }

    /**
     * 書き出す内容をすべて伏せ字にした、同じ宛先の Output を返す。
     */
    public function masked(CredentialMasker $masker): self
    {
        return new self($this->stdout, $this->stderr, $masker);
    }

    public function write(string $text): void
    {
        fwrite($this->stdout, $this->mask($text));
    }

    public function line(string $text = ''): void
    {
        fwrite($this->stdout, $this->mask($text) . PHP_EOL);
    }

    public function error(string $text): void
    {
        fwrite($this->stderr, $this->mask($text) . PHP_EOL);
    }

    private function mask(string $text): string
    {
        return $this->masker?->mask($text) ?? $text;
    }
}
