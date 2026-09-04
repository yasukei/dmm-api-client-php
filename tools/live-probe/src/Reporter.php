<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

/**
 * 実行結果を集計して、標準出力向けのサマリと失敗一覧のファイルを作る。
 *
 * 同じ食い違いは何百件も出るので、DTO のどのフィールドで起きたかで束ねる。
 * 直すべき箇所の数が一目で分かるようにするため。
 */
final readonly class Reporter
{
    /** 1 つのフィールドについて載せる、再現用の実例の数。 */
    private const int SAMPLES_PER_PATH = 3;

    /** 1 つのフィールドについて failures.md に並べる、エラーメッセージの種類の数。 */
    private const int MESSAGES_PER_PATH = 5;

    /** 実例に載せる、実際の値の最大文字数。 */
    private const int VALUE_LIMIT = 300;

    /** @param list<Record> $records */
    public function __construct(
        private array $records,
        private RunDirectory $run,
    ) {
    }

    /**
     * 検証に失敗した、あるいは通信に失敗したリクエストがあるか。
     */
    public function hasFailures(): bool
    {
        foreach ($this->records as $record) {
            if ($record->isFailure()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [
            'requests' => count($this->records),
            'ok' => 0,
            'cached' => 0,
            'api-error' => 0,
            'transport-error' => 0,
            'validation-ok' => 0,
            'validation-failed' => 0,
            'validation-skipped' => 0,
        ];

        foreach ($this->records as $record) {
            $counts[$record->outcome] = ($counts[$record->outcome] ?? 0) + 1;
            $counts['validation-' . $record->validation] = ($counts['validation-' . $record->validation] ?? 0) + 1;

            if ($record->cached) {
                $counts['cached']++;
            }
        }

        return $counts;
    }

    /**
     * 標準出力に出すサマリ。
     */
    public function summary(): string
    {
        $counts = $this->counts();
        $lines = [
            '=== live-probe summary ===',
            sprintf(
                'requests: %d   ok: %d   api-error: %d   transport-error: %d   (not re-fetched: %d)',
                $counts['requests'],
                $counts['ok'],
                $counts['api-error'],
                $counts['transport-error'],
                $counts['cached'],
            ),
            sprintf(
                'validation  ok: %d   failed: %d   skipped: %d',
                $counts['validation-ok'],
                $counts['validation-failed'],
                $counts['validation-skipped'],
            ),
            '',
        ];

        $groups = $this->failureGroups();

        if ($groups === []) {
            $lines[] = 'No validation failures.';
        } else {
            $lines[] = sprintf('validation failures by path (%d paths):', count($groups));

            foreach ($groups as $group) {
                $lines[] = sprintf(
                    '  %-52s %5d  %s',
                    $group['path'],
                    $group['requests'],
                    self::truncate($group['messages'][0]['message'], 90),
                );
            }
        }

        $apiErrors = $this->apiErrorGroups();

        if ($apiErrors !== []) {
            $lines[] = '';
            $lines[] = sprintf('api errors (%d kinds):', count($apiErrors));

            foreach ($apiErrors as $group) {
                $lines[] = sprintf('  %5d  %s', $group['requests'], self::truncate($group['message'], 100));
            }
        }

        $lines[] = '';
        $lines[] = 'details: ' . $this->run->file('failures.md');

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * failures.json と failures.md を書き出す。
     */
    public function writeFailures(): void
    {
        $groups = $this->failureGroups();
        $apiErrors = $this->apiErrorGroups();
        $transportErrors = $this->transportErrors();

        $this->run->writeRun([
            'summary' => $this->counts(),
        ] + $this->existingRun());

        file_put_contents($this->run->file('failures.json'), Json::encode([
            'summary' => $this->counts(),
            'validationFailures' => $groups,
            'apiErrors' => $apiErrors,
            'transportErrors' => $transportErrors,
        ]) . PHP_EOL);

        file_put_contents($this->run->file('failures.md'), $this->markdown($groups, $apiErrors, $transportErrors));
    }

    /**
     * @param list<array{path: string, requests: int, messages: list<array{message: string, count: int}>, samples: list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>}> $groups
     * @param list<array{requests: int, message: string, status: int|null, samples: list<string>}>                                                                                                                          $apiErrors
     * @param list<array{label: string, uri: string, message: string}>                                                                                                                                                      $transportErrors
     */
    private function markdown(array $groups, array $apiErrors, array $transportErrors): string
    {
        $counts = $this->counts();
        $lines = [
            '# live-probe failures',
            '',
            sprintf('- run: `%s`', $this->run->path),
            sprintf(
                '- requests: %d (ok %d / api-error %d / transport-error %d / not re-fetched %d)',
                $counts['requests'],
                $counts['ok'],
                $counts['api-error'],
                $counts['transport-error'],
                $counts['cached'],
            ),
            sprintf(
                '- validation: ok %d / failed %d / skipped %d',
                $counts['validation-ok'],
                $counts['validation-failed'],
                $counts['validation-skipped'],
            ),
            '',
            '## Validation failures',
            '',
        ];

        if ($groups === []) {
            $lines[] = 'None.';
        }

        foreach ($groups as $group) {
            $lines[] = sprintf('### `%s` (%d requests)', $group['path'], $group['requests']);
            $lines[] = '';

            foreach (array_slice($group['messages'], 0, self::MESSAGES_PER_PATH) as $message) {
                $lines[] = sprintf('- %d× %s', $message['count'], $message['message']);
            }

            if (count($group['messages']) > self::MESSAGES_PER_PATH) {
                $lines[] = sprintf(
                    '- …and %d more message(s); see failures.json.',
                    count($group['messages']) - self::MESSAGES_PER_PATH,
                );
            }

            $lines[] = '';

            foreach ($group['samples'] as $sample) {
                $lines[] = sprintf('- `%s`', $sample['file'] ?? '(not saved)');
                $lines[] = sprintf('  - request: %s', $sample['label']);
                $lines[] = sprintf('  - path: `%s`', $sample['path']);
                $lines[] = sprintf('  - value: `%s`', $sample['value']);
            }

            $lines[] = '';
        }

        $lines[] = '## API errors';
        $lines[] = '';

        if ($apiErrors === []) {
            $lines[] = 'None.';
        }

        foreach ($apiErrors as $group) {
            $lines[] = sprintf('### %s (%d requests)', $group['message'], $group['requests']);
            $lines[] = '';

            foreach ($group['samples'] as $sample) {
                $lines[] = sprintf('- %s', $sample);
            }

            $lines[] = '';
        }

        $lines[] = '## Transport errors';
        $lines[] = '';

        if ($transportErrors === []) {
            $lines[] = 'None.';
        }

        foreach ($transportErrors as $error) {
            $lines[] = sprintf('- %s', $error['label']);
            $lines[] = sprintf('  - %s', $error['message']);
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * 検証エラーを、DTO のフィールド（配列の添字を `*` に均したパス）で束ねる。
     *
     * @return list<array{path: string, requests: int, messages: list<array{message: string, count: int}>, samples: list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>}>
     */
    private function failureGroups(): array
    {
        /** @var array<string, array{requests: int, messages: array<string, int>, records: list<array{Record, array{path: string, message: string}}>}> $grouped */
        $grouped = [];

        foreach ($this->records as $record) {
            if ($record->validation !== Record::VALIDATION_FAILED) {
                continue;
            }

            /** @var array<string, true> $seen このリクエストで既に数えたパス */
            $seen = [];

            foreach ($record->errors as $error) {
                $path = self::normalizePath($error['path']);
                $grouped[$path] ??= ['requests' => 0, 'messages' => [], 'records' => []];

                if (! isset($seen[$path])) {
                    $grouped[$path]['requests']++;
                    $seen[$path] = true;
                    $grouped[$path]['records'][] = [$record, $error];
                }

                $message = self::normalizeMessage($error['message']);
                $grouped[$path]['messages'][$message] = ($grouped[$path]['messages'][$message] ?? 0) + 1;
            }
        }

        uasort($grouped, static fn (array $a, array $b): int => $b['requests'] <=> $a['requests']);

        $groups = [];

        foreach ($grouped as $path => $group) {
            arsort($group['messages']);
            $messages = [];

            foreach ($group['messages'] as $message => $count) {
                $messages[] = ['message' => $message, 'count' => $count];
            }

            $groups[] = [
                'path' => $path,
                'requests' => $group['requests'],
                'messages' => $messages,
                'samples' => $this->samples(array_slice($group['records'], 0, self::SAMPLES_PER_PATH)),
            ];
        }

        return $groups;
    }

    /**
     * @param list<array{Record, array{path: string, message: string}}> $records
     *
     * @return list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>
     */
    private function samples(array $records): array
    {
        $samples = [];

        foreach ($records as [$record, $error]) {
            $samples[] = [
                'file' => $record->file,
                'label' => $record->label(),
                'uri' => $record->uri,
                'path' => $error['path'],
                'message' => $error['message'],
                'value' => $this->valueAt($record, $error['path']),
            ];
        }

        return $samples;
    }

    /**
     * 保存したレスポンスから、エラーになった位置の実際の値を取り出す。
     *
     * DTO を直すときに「どんな値が来ていたのか」がその場で分かるようにするため。
     */
    private function valueAt(Record $record, string $path): string
    {
        if ($record->file === null) {
            return '(not saved)';
        }

        $body = $this->run->read($record->file);
        $value = $body === null ? null : Json::decode($body);

        if ($value === null) {
            return '(unreadable)';
        }

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return '(not found)';
            }

            $value = $value[$segment];
        }

        return self::truncate(Json::encodeValue($value), self::VALUE_LIMIT);
    }

    /**
     * @return list<array{requests: int, message: string, status: int|null, samples: list<string>}>
     */
    private function apiErrorGroups(): array
    {
        /** @var array<string, array{requests: int, status: int|null, samples: list<string>}> $grouped */
        $grouped = [];

        foreach ($this->records as $record) {
            if ($record->outcome !== Record::OUTCOME_API_ERROR) {
                continue;
            }

            $message = $record->message ?? 'unknown';
            $grouped[$message] ??= ['requests' => 0, 'status' => $record->httpStatus, 'samples' => []];
            $grouped[$message]['requests']++;

            if (count($grouped[$message]['samples']) < self::SAMPLES_PER_PATH) {
                $grouped[$message]['samples'][] = $record->label();
            }
        }

        uasort($grouped, static fn (array $a, array $b): int => $b['requests'] <=> $a['requests']);

        $groups = [];

        foreach ($grouped as $message => $group) {
            $groups[] = [
                'requests' => $group['requests'],
                'message' => $message,
                'status' => $group['status'],
                'samples' => $group['samples'],
            ];
        }

        return $groups;
    }

    /**
     * @return list<array{label: string, uri: string, message: string}>
     */
    private function transportErrors(): array
    {
        $errors = [];

        foreach ($this->records as $record) {
            if ($record->outcome === Record::OUTCOME_TRANSPORT_ERROR) {
                $errors[] = [
                    'label' => $record->label(),
                    'uri' => $record->uri,
                    'message' => $record->message ?? '',
                ];
            }
        }

        return $errors;
    }

    /**
     * 既に書いてある run.json の内容。サマリだけを差し替えるために読み直す。
     *
     * @return array<string, mixed>
     */
    private function existingRun(): array
    {
        $contents = $this->run->read(RunDirectory::RUN);
        $decoded = $contents === null ? null : Json::decode($contents);
        $run = [];

        foreach ($decoded ?? [] as $key => $value) {
            if (is_string($key)) {
                $run[$key] = $value;
            }
        }

        return $run;
    }

    /**
     * `result.items.12.date` を `result.items.*.date` に均す。
     */
    private static function normalizePath(string $path): string
    {
        $segments = [];

        foreach (explode('.', $path) as $segment) {
            $segments[] = preg_match('/^\d+$/', $segment) === 1 ? '*' : $segment;
        }

        return implode('.', $segments);
    }

    /**
     * メッセージは実際の値を含むので、そのまま数える。
     *
     * 値をまとめてしまうと「どんな値が来ていたか」が失われる。同じフィールドで
     * 値だけが違うメッセージは、パスで束ねた中の内訳として並ぶ。
     */
    private static function normalizeMessage(string $message): string
    {
        return self::truncate($message, 200);
    }

    private static function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . '…';
    }
}
