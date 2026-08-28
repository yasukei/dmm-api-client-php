<?php

declare(strict_types=1);

namespace Tests\Support;

use DmmApiClient\Console\Output;
use RuntimeException;

/**
 * メモリ上のストリームへ書き出す {@see Output} と、その内容を読み返す手段。
 */
final class CapturingOutput
{
    public readonly Output $output;

    /** @var resource */
    private $stdoutStream;

    /** @var resource */
    private $stderrStream;

    public function __construct()
    {
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        if ($stdout === false || $stderr === false) {
            throw new RuntimeException('Could not open an in-memory stream.');
        }

        $this->stdoutStream = $stdout;
        $this->stderrStream = $stderr;
        $this->output = new Output($stdout, $stderr);
    }

    public function stdout(): string
    {
        return $this->read($this->stdoutStream);
    }

    public function stderr(): string
    {
        return $this->read($this->stderrStream);
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
