<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\Console\Output;

/**
 * 進捗とレポートの書き出し先。
 *
 * 進捗は標準エラー出力、レポートは標準出力へ流す。認証情報の伏せ字は
 * {@see Output} 側でまとめて適用されるため、ここでは何もしない。
 */
final readonly class Console
{
    public function __construct(
        private Output $output,
    ) {
    }

    public function progress(string $line): void
    {
        $this->output->error($line);
    }

    public function report(string $text): void
    {
        $this->output->write($text);
    }
}
