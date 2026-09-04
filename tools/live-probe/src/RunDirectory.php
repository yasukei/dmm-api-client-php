<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

/**
 * 1 回の実行分の出力をまとめるディレクトリ。
 *
 * 実行のたびに `<出力ルート>/<日時>` を新しく作る。過去の実行を上書きしないため、
 * また `--revalidate` でどの実行を検証し直すかを指定できるようにするため。
 */
final readonly class RunDirectory
{
    /** 取得したレスポンスの一覧。1 行 1 リクエスト。 */
    public const string MANIFEST = 'manifest.jsonl';

    /** 実行条件と集計。 */
    public const string RUN = 'run.json';

    private function __construct(
        public string $path,
    ) {
    }

    /**
     * @throws ProbeException
     */
    public static function create(string $root, string $stamp): self
    {
        $path = rtrim($root, '/') . '/' . $stamp;

        if (! is_dir($path) && ! mkdir($path, 0o755, true) && ! is_dir($path)) {
            throw new ProbeException(sprintf('Could not create the run directory "%s".', $path));
        }

        return new self($path);
    }

    /**
     * @throws ProbeException
     */
    public static function open(string $path): self
    {
        if (! is_dir($path)) {
            throw new ProbeException(sprintf('Run directory "%s" does not exist.', $path));
        }

        return new self(rtrim($path, '/'));
    }

    /**
     * 出力ルートの中で最も新しい実行。ディレクトリ名が日時なので名前順で選べる。
     */
    public static function latest(string $root): ?self
    {
        if (! is_dir($root)) {
            return null;
        }

        $candidates = [];

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_dir($root . '/' . $entry)) {
                $candidates[] = $entry;
            }
        }

        if ($candidates === []) {
            return null;
        }

        sort($candidates);

        return new self(rtrim($root, '/') . '/' . end($candidates));
    }

    public function file(string $relative): string
    {
        return $this->path . '/' . $relative;
    }

    /**
     * レスポンスを保存する。必要ならサブディレクトリを作る。
     *
     * @throws ProbeException
     */
    public function save(string $relative, string $contents): void
    {
        $path = $this->file($relative);
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new ProbeException(sprintf('Could not create the directory "%s".', $directory));
        }

        if (file_put_contents($path, $contents) === false) {
            throw new ProbeException(sprintf('Could not write "%s".', $path));
        }
    }

    public function has(string $relative): bool
    {
        return is_file($this->file($relative));
    }

    public function read(string $relative): ?string
    {
        $contents = @file_get_contents($this->file($relative));

        return $contents === false ? null : $contents;
    }

    /**
     * 1 件ずつ追記する。途中で止まっても、それまでの結果が残るようにするため。
     */
    public function appendRecord(Record $record): void
    {
        file_put_contents(
            $this->file(self::MANIFEST),
            Json::encode($record->toArray(), pretty: false) . PHP_EOL,
            FILE_APPEND,
        );
    }

    /**
     * @param list<Record> $records
     */
    public function writeRecords(array $records): void
    {
        $lines = '';

        foreach ($records as $record) {
            $lines .= Json::encode($record->toArray(), pretty: false) . PHP_EOL;
        }

        file_put_contents($this->file(self::MANIFEST), $lines);
    }

    /**
     * @return list<Record>
     *
     * @throws ProbeException
     */
    public function records(): array
    {
        $contents = $this->read(self::MANIFEST);

        if ($contents === null) {
            throw new ProbeException(sprintf('"%s" has no %s.', $this->path, self::MANIFEST));
        }

        $records = [];

        foreach (explode(PHP_EOL, $contents) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $decoded = Json::decode($line);

            if ($decoded !== null) {
                $records[] = Record::fromArray($decoded);
            }
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function writeRun(array $data): void
    {
        file_put_contents($this->file(self::RUN), Json::encode($data) . PHP_EOL);
    }
}
