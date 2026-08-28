<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

/**
 * 標準出力・標準エラー出力への書き出し。
 *
 * API のレスポンスは標準出力へ、それ以外の診断メッセージは標準エラー出力へ書き出す。
 * パイプでつないだときにレスポンスだけが流れるようにするための分離。
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
    ) {
    }

    public function write(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    public function line(string $text = ''): void
    {
        fwrite($this->stdout, $text . PHP_EOL);
    }

    public function error(string $text): void
    {
        fwrite($this->stderr, $text . PHP_EOL);
    }
}
