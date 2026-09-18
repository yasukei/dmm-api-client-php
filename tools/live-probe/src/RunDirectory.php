<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use JsonException;

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

        $entries = scandir($root);

        foreach ($entries !== false ? $entries : [] as $entry) {
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

        // 書き込んでから名前を付け替える。実行は数十分に及び、途中で止められることを
        // 前提にしている。書き込みの途中で死ぬと、--resume は切れたファイルを
        // 取得済みとみなしてしまう。名前が付くのは中身が揃ったあとだけにする。
        $temporary = $path . '.part';

        if (file_put_contents($temporary, $contents) === false) {
            throw new ProbeException(sprintf('Could not write "%s".', $temporary));
        }

        if (! rename($temporary, $path)) {
            @unlink($temporary);

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
     * 保存済みのレスポンスを、名前の順に 1 つずつ読み出す。
     *
     * まとめて配列に載せない。1 フロア分でも数十 MB になり、数える側は 1 つずつ見れば足りるため。
     *
     * @param string $group  サブディレクトリ名（例: ItemList）
     * @param string $prefix ファイル名の先頭（例: FANZA__digital__videoa-43__）
     *
     * @return iterable<string>
     */
    public function bodies(string $group, string $prefix): iterable
    {
        $paths = glob($this->file($group . '/' . $prefix . '*.json'));

        foreach ($paths !== false ? $paths : [] as $path) {
            $contents = @file_get_contents($path);

            if ($contents !== false) {
                yield $contents;
            }
        }
    }

    /**
     * 1 件ずつ追記する。途中で止まっても、それまでの結果が残るようにするため。
     *
     * @throws JsonException 記録を JSON にできなかった場合
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
     * 同じレスポンスについての記録を 1 件にまとめる。あとの記録を残す。
     *
     * 取得中は 1 件ずつ追記する。途中で止まってもそれまでの結果が残るようにするためだが、
     * `--resume` では取得し直さなかった分も改めて記録するので、同じレスポンスの行が 2 つ並ぶ。
     * 1 レスポンス 1 行に戻したうえで集計する。
     *
     * ボディを保存できなかった記録（通信エラー）はファイルを持たないので、送った URI で見分ける。
     *
     * @param list<Record> $records
     *
     * @return list<Record>
     */
    public static function latestPerResponse(array $records): array
    {
        $latest = [];

        foreach ($records as $record) {
            // 既にあるキーへ入れ直しても位置は動かない。先に現れた順序のまま、中身だけが新しくなる。
            $latest[$record->file ?? $record->uri] = $record;
        }

        return array_values($latest);
    }

    /**
     * @param list<Record> $records
     *
     * @throws JsonException 記録を JSON にできなかった場合
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
     *
     * @throws JsonException 内容を JSON にできなかった場合
     */
    public function writeRun(array $data): void
    {
        file_put_contents($this->file(self::RUN), Json::encode($data) . PHP_EOL);
    }
}
