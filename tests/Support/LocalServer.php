<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * PHP の組み込みサーバーを、127.0.0.1 の空いているポートで動かす。
 */
final class LocalServer
{
    private const float STARTUP_TIMEOUT_SECONDS = 5.0;

    /**
     * @param resource $process
     */
    private function __construct(
        private $process,
        public readonly string $baseUri,
    ) {}

    /**
     * @param string $router 組み込みサーバーに渡すルータースクリプトのパス
     */
    public static function start(string $router): self
    {
        $port = self::findFreePort();
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes,
        );

        if ($process === false) {
            throw new RuntimeException('The PHP built-in server could not be started.');
        }

        $server = new self($process, 'http://127.0.0.1:' . $port);
        $server->waitUntilListening($port);

        return $server;
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');

        if ($socket === false) {
            throw new RuntimeException('No free port could be found.');
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private function waitUntilListening(int $port): void
    {
        $deadline = microtime(true) + self::STARTUP_TIMEOUT_SECONDS;

        while (microtime(true) < $deadline) {
            // 起動前の接続は拒否されて warning になる。@ で抑えても PHPUnit には拾われる。
            set_error_handler(static fn(): bool => true);

            try {
                $connection = fsockopen('127.0.0.1', $port, timeout: 0.1);
            } finally {
                restore_error_handler();
            }

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        $this->stop();

        throw new RuntimeException(sprintf('The PHP built-in server did not start listening on port %d.', $port));
    }
}
