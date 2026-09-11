<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

/**
 * 実行結果を集計して、標準出力向けのサマリと失敗一覧のファイルを作る。
 *
 * 同じ食い違いは何百件も出るので、DTO のどのフィールドで起きたかで束ねる。
 * 直すべき箇所の数が一目で分かるようにするため。
 *
 * @phpstan-type FilterRow array{floor: string, floorId: string, name: string, value: string, verdict: string, returned: int, matching: int, totalCount: int|null, unfiltered: int|null, file: string|null, label: string, message: string|null}
 */
final readonly class Reporter
{
    /** 1 つのフィールドについて載せる、再現用の実例の数。 */
    private const int SAMPLES_PER_PATH = 3;

    /** 1 つのフィールドについて failures.md に並べる、エラーメッセージの種類の数。 */
    private const int MESSAGES_PER_PATH = 5;

    /** 実例に載せる、実際の値の最大文字数。 */
    private const int VALUE_LIMIT = 300;

    private const string ARTICLE_INTRO =
        'Each floor is swept again with the most frequent id of every article the floor\'s items actually '
        . 'carry. An id that draws nothing is retried once with the next most frequent one, and the attempt '
        . 'it replaces reads as retried.';

    private const string STOCK_INTRO =
        'Every mono floor is swept again once per mono_stock value, including the values the enum says '
        . 'cannot be used to filter — that was read off a handful of responses, never checked floor by floor.';

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
            'unexpected-ok' => 0,
            'validation-ok' => 0,
            'validation-failed' => 0,
            'validation-skipped' => 0,
            'unknown-keys' => 0,
        ];

        foreach ($this->records as $record) {
            $counts[$record->outcome] = ($counts[$record->outcome] ?? 0) + 1;
            $counts['validation-' . $record->validation] = ($counts['validation-' . $record->validation] ?? 0) + 1;

            if ($record->cached) {
                $counts['cached']++;
            }

            if ($record->unknownKeys !== []) {
                $counts['unknown-keys']++;
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
            ...self::unexpectedOk($counts),
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

        $unknown = $this->unknownKeyGroups();

        if ($unknown !== []) {
            $lines[] = '';
            $lines[] = sprintf(
                'keys the DTOs do not know (%d paths, %d responses):',
                count($unknown),
                $counts['unknown-keys'],
            );

            foreach ($unknown as $group) {
                $lines[] = sprintf('  %-52s %5d', $group['path'], $group['requests']);
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

        foreach ([
            'article filters' => $this->articleFilters(),
            'mono_stock filters' => $this->stockFilters(),
        ] as $heading => $filters) {
            $groups = self::filterGroups($filters);

            if ($groups === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = sprintf('%s (%d kinds):', $heading, count($groups));

            foreach ($groups as $group) {
                $verdicts = [];

                foreach ($group['verdicts'] as $verdict => $count) {
                    $verdicts[] = sprintf('%s %d', $verdict, $count);
                }

                $lines[] = sprintf('  %-20s %5d  %s', $group['name'], $group['requests'], implode('  ', $verdicts));
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
        $unknownKeys = $this->unknownKeyGroups();
        $apiErrors = $this->apiErrorGroups();
        $transportErrors = $this->transportErrors();
        $articleFilters = $this->articleFilters();
        $stockFilters = $this->stockFilters();

        $this->run->writeRun([
            'summary' => $this->counts(),
        ] + $this->existingRun());

        file_put_contents($this->run->file('failures.json'), Json::encode([
            'summary' => $this->counts(),
            'validationFailures' => $groups,
            'unknownKeys' => $unknownKeys,
            'apiErrors' => $apiErrors,
            'transportErrors' => $transportErrors,
            'articleFilters' => $articleFilters,
            'monoStockFilters' => $stockFilters,
        ]) . PHP_EOL);

        file_put_contents(
            $this->run->file('failures.md'),
            $this->markdown($groups, $unknownKeys, $apiErrors, $transportErrors, $articleFilters, $stockFilters),
        );
    }

    /**
     * @param list<array{path: string, requests: int, messages: list<array{message: string, count: int}>, samples: list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>}> $groups
     * @param list<array{path: string, requests: int, messages: list<array{message: string, count: int}>, samples: list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>}> $unknownKeys
     * @param list<array{requests: int, message: string, status: int|null, samples: list<string>}>                                                                                                                          $apiErrors
     * @param list<array{label: string, uri: string, message: string}>                                                                                                                                                      $transportErrors
     * @param list<FilterRow>                                                                                                                                                                                        $articleFilters
     * @param list<FilterRow>                                                                                                                                                                                        $stockFilters
     */
    private function markdown(
        array $groups,
        array $unknownKeys,
        array $apiErrors,
        array $transportErrors,
        array $articleFilters,
        array $stockFilters,
    ): string {
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
            ...array_map(static fn (string $line): string => '- ' . $line, self::unexpectedOk($counts)),
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

        $lines[] = '## Keys the DTOs do not know';
        $lines[] = '';
        $lines[] = sprintf(
            'Found by mapping each response a second time with a mapper that rejects unknown keys. '
            . 'These do not fail the run — the DTOs ignore them — but they are what DMM returns and the library does not expose. '
            . '(%d responses carry at least one.)',
            $counts['unknown-keys'],
        );
        $lines[] = '';

        if ($unknownKeys === []) {
            $lines[] = 'None.';
            $lines[] = '';
        }

        foreach ($unknownKeys as $group) {
            $lines[] = sprintf('### `%s` (%d responses)', $group['path'], $group['requests']);
            $lines[] = '';

            foreach ($group['samples'] as $sample) {
                $lines[] = sprintf('- `%s`', $sample['file'] ?? '(not saved)');
                $lines[] = sprintf('  - request: %s', $sample['label']);
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

        return implode(PHP_EOL, [
            ...$lines,
            '',
            ...self::filterMarkdown('Article filters', self::ARTICLE_INTRO, 'article', 'article_id', $articleFilters),
            '',
            ...self::filterMarkdown('Stock filters', self::STOCK_INTRO, 'mono_stock', null, $stockFilters),
        ]) . PHP_EOL;
    }

    /**
     * 検証エラーを、DTO のフィールド（配列の添字を `*` に均したパス）で束ねる。
     *
     * @return list<array{path: string, requests: int, messages: list<array{message: string, count: int}>, samples: list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>}>
     */
    private function failureGroups(): array
    {
        return $this->groups(static fn (Record $record): array => $record->validation === Record::VALIDATION_FAILED
            ? $record->errors
            : []);
    }

    /**
     * DTO が知らないキーを、同じ要領でパスごとに束ねる。
     *
     * @return list<array{path: string, requests: int, messages: list<array{message: string, count: int}>, samples: list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>}>
     */
    private function unknownKeyGroups(): array
    {
        return $this->groups(static fn (Record $record): array => $record->unknownKeys);
    }

    /**
     * @param callable(Record): list<array{path: string, message: string}> $select
     *
     * @return list<array{path: string, requests: int, messages: list<array{message: string, count: int}>, samples: list<array{file: string|null, label: string, uri: string, path: string, message: string, value: string}>}>
     */
    private function groups(callable $select): array
    {
        /** @var array<string, array{requests: int, messages: array<string, int>, records: list<array{Record, array{path: string, message: string}}>}> $grouped */
        $grouped = [];

        foreach ($this->records as $record) {
            /** @var array<string, true> $seen このリクエストで既に数えたパス */
            $seen = [];

            foreach ($select($record) as $error) {
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

    /**
     * エラーを引くつもりが通ってしまったリクエストの件数。
     *
     * 起きたときだけ 1 行として現れる。通常の実行では 0 で、常に出しても読み手の役に立たない。
     *
     * @param array<string, int> $counts
     *
     * @return list<string>
     */
    private static function unexpectedOk(array $counts): array
    {
        $unexpected = $counts['unexpected-ok'] ?? 0;

        return $unexpected === 0 ? [] : [sprintf(
            'requests that should have been rejected but succeeded: %d',
            $unexpected,
        )];
    }

    /**
     * `article` を指定した対象について、フィルタが実際に効いたかを判定する。
     *
     * 見るのは件数ではなく中身。API が `article` を黙って無視して通常の結果を返す可能性があり、
     * 件数だけでは「効いた」と「無視された」を区別できないため、返ってきた商品が
     * その ID を実際に持っているかを数える。
     *
     * 絞り込み無しの件数は、同じフロアの通常の `ItemList` から借りる。追加のリクエストは要らない。
     *
     * @return list<FilterRow>
     */
    private function articleFilters(): array
    {
        $unfiltered = $this->unfilteredTotals();
        $filters = [];

        foreach ($this->records as $record) {
            if ($record->group !== Planner::ARTICLES) {
                continue;
            }

            $article = $record->context['article'] ?? null;
            $id = $record->context['article_id'] ?? null;

            if ($article === null || $id === null) {
                continue;
            }

            $items = ArticleTally::itemsOf($this->decoded($record));
            $matching = 0;

            foreach ($items as $item) {
                if (ArticleTally::carries($item, $article, $id)) {
                    $matching++;
                }
            }

            $filters[] = [
                'floor' => $record->context['floor'] ?? '',
                'floorId' => $record->context['floor_id'] ?? '',
                'name' => $article,
                'value' => $id,
                'verdict' => self::verdict($record, count($items), $matching),
                'returned' => count($items),
                'matching' => $matching,
                'totalCount' => $record->totalCount,
                'unfiltered' => $unfiltered[$record->context['floor_id'] ?? ''] ?? null,
                'file' => $record->file,
                'label' => $record->label(),
                'message' => $record->message,
            ];
        }

        return self::markRetried($filters);
    }

    /**
     * `mono_stock` を指定した対象について、その値で絞り込めたかを判定する。
     *
     * 見方は `article` と同じで、件数ではなく中身を見る。商品が持つ `stock` が、指定した値と
     * 一致しているかを数える。`article` と違って引き直しは無い。値は enum の全種を送るので、
     * 次に試すものが残らないため。
     *
     * 束ねる単位は指定した値そのもの。`article` では分類の名前（genre、label）で束ねるが、
     * こちらは分類が 1 つしかなく、値ごとに効くかどうかが変わる。
     *
     * @return list<FilterRow>
     */
    private function stockFilters(): array
    {
        $unfiltered = $this->unfilteredTotals();
        $filters = [];

        foreach ($this->records as $record) {
            if ($record->group !== Planner::MONO_STOCK) {
                continue;
            }

            $stock = $record->context['mono_stock'] ?? null;

            if ($stock === null) {
                continue;
            }

            $items = ArticleTally::itemsOf($this->decoded($record));
            $matching = 0;

            foreach ($items as $item) {
                if (($item['stock'] ?? null) === $stock) {
                    $matching++;
                }
            }

            $filters[] = [
                'floor' => $record->context['floor'] ?? '',
                'floorId' => $record->context['floor_id'] ?? '',
                'name' => $stock,
                'value' => $stock,
                'verdict' => self::verdict($record, count($items), $matching),
                'returned' => count($items),
                'matching' => $matching,
                'totalCount' => $record->totalCount,
                'unfiltered' => $unfiltered[$record->context['floor_id'] ?? ''] ?? null,
                'file' => $record->file,
                'label' => $record->label(),
                'message' => $record->message,
            ];
        }

        return $filters;
    }

    /**
     * 引き直された試行に印を付ける。
     *
     * 0 件だった試行は別の ID で引き直される。その 1 本目を `empty` のまま数えると、
     * 同じ分類が「半分は効かなかった」ように見えてしまう。結論は後から引いた方に載るので、
     * 先に引いた方は `retried` として分ける。
     *
     * 同じ分類・同じフロアで、あとに別の ID の試行があるものだけを対象にする。
     *
     * @param list<FilterRow> $filters
     *
     * @return list<FilterRow>
     */
    private static function markRetried(array $filters): array
    {
        foreach ($filters as $at => $filter) {
            foreach (array_slice($filters, $at + 1) as $later) {
                if ($later['floorId'] === $filter['floorId']
                    && $later['name'] === $filter['name']
                    && $later['value'] !== $filter['value']
                ) {
                    $filters[$at]['verdict'] = 'retried';

                    break;
                }
            }
        }

        return $filters;
    }

    /**
     * 絞り込みを掛けなかった場合の、フロアごとの総件数。
     *
     * @return array<string, int>
     */
    private function unfilteredTotals(): array
    {
        $totals = [];

        foreach ($this->records as $record) {
            $floorId = $record->context['floor_id'] ?? null;

            if ($record->group === 'ItemList' && $floorId !== null && $record->totalCount !== null) {
                $totals[$floorId] ??= $record->totalCount;
            }
        }

        return $totals;
    }

    /**
     * 商品が実際にその分類を持っているかで判定する。
     *
     * - `rejected`: API がエラーを返した。その分類は `article` に指定できない
     * - `empty`: 0 件で返った。指定は通ったが該当が無い（無視されたのではない）
     * - `honored`: 返った商品がすべてその ID を持つ。絞り込めている
     * - `ignored`: どの商品もその ID を持たない。`article` が読み捨てられている
     * - `partial`: 一部だけが持つ。上の 3 つのどれとも言えず、実物を見る必要がある
     *
     * このあと {@see self::markRetried()} が、引き直された試行を `retried` に振り替える。
     */
    private static function verdict(Record $record, int $returned, int $matching): string
    {
        return match (true) {
            $record->outcome === Record::OUTCOME_API_ERROR => 'rejected',
            $returned === 0 => 'empty',
            $matching === $returned => 'honored',
            $matching === 0 => 'ignored',
            default => 'partial',
        };
    }

    /**
     * 分類ごとに、判定の内訳を数える。
     *
     * @param list<FilterRow> $filters
     *
     * @return list<array{name: string, requests: int, verdicts: array<string, int>}>
     */
    private static function filterGroups(array $filters): array
    {
        /** @var array<string, array{requests: int, verdicts: array<string, int>}> $grouped */
        $grouped = [];

        foreach ($filters as $filter) {
            $grouped[$filter['name']] ??= ['requests' => 0, 'verdicts' => []];
            $grouped[$filter['name']]['requests']++;
            $verdict = $filter['verdict'];
            $grouped[$filter['name']]['verdicts'][$verdict] =
                ($grouped[$filter['name']]['verdicts'][$verdict] ?? 0) + 1;
        }

        uasort($grouped, static fn (array $a, array $b): int => $b['requests'] <=> $a['requests']);

        $groups = [];

        foreach ($grouped as $name => $group) {
            arsort($group['verdicts']);
            $groups[] = ['name' => $name, 'requests' => $group['requests'], 'verdicts' => $group['verdicts']];
        }

        return $groups;
    }

    /**
     * 保存したレスポンスを読み直す。
     *
     * @return array<mixed>|null
     */
    private function decoded(Record $record): ?array
    {
        if ($record->file === null) {
            return null;
        }

        $body = $this->run->read($record->file);

        return $body === null ? null : Json::decode($body);
    }

    /**
     * 絞り込みの判定結果を、指定した値ごとの内訳と 1 行 1 リクエストの表にする。
     *
     * 判定が何を意味するかは {@see self::verdict()} にある。`honored` が並べば絞り込めており、
     * `ignored` なら指定が読み捨てられている。`rejected` と `empty` の違いも読めるようにしておく。
     * 前者は指定そのものを受け付けず、後者は受け付けたうえで該当が無い。
     *
     * @param string          $nameHeader  束ねる単位の見出し（例: article）
     * @param string|null      $valueHeader 指定した値の見出し（例: article_id）。束ねる単位と同じなら null
     * @param list<FilterRow> $filters
     *
     * @return list<string>
     */
    private static function filterMarkdown(
        string $title,
        string $intro,
        string $nameHeader,
        ?string $valueHeader,
        array $filters,
    ): array {
        $lines = ['## ' . $title, '', $intro, ''];
        $lines[] = 'A response counts as honored only when every item it returns matches what was asked for — '
            . 'a filter the API quietly drops would still answer with items.';
        $lines[] = '';

        if ($filters === []) {
            $lines[] = 'None.';

            return $lines;
        }

        $lines[] = sprintf('| %s | requests | verdicts |', $nameHeader);
        $lines[] = '| --- | --- | --- |';

        foreach (self::filterGroups($filters) as $group) {
            $verdicts = [];

            foreach ($group['verdicts'] as $verdict => $count) {
                $verdicts[] = sprintf('%s %d', $verdict, $count);
            }

            $lines[] = sprintf('| `%s` | %d | %s |', $group['name'], $group['requests'], implode(', ', $verdicts));
        }

        $columns = ['floor', $nameHeader, ...($valueHeader === null ? [] : [$valueHeader]),
            'verdict', 'items', 'matching', 'total_count', 'unfiltered'];

        $lines[] = '';
        $lines[] = '| ' . implode(' | ', $columns) . ' |';
        $lines[] = '| ' . implode(' | ', array_fill(0, count($columns), '---')) . ' |';

        foreach ($filters as $filter) {
            $cells = [
                sprintf('%s (%s)', $filter['floor'], $filter['floorId']),
                sprintf('`%s`', $filter['name']),
                ...($valueHeader === null ? [] : [sprintf('`%s`', $filter['value'])]),
                $filter['verdict'],
                (string) $filter['returned'],
                (string) $filter['matching'],
                $filter['totalCount'] === null ? '-' : (string) $filter['totalCount'],
                $filter['unfiltered'] === null ? '-' : (string) $filter['unfiltered'],
            ];

            $lines[] = '| ' . implode(' | ', $cells) . ' |';
        }

        return $lines;
    }

    private static function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) <= $limit ? $value : mb_substr($value, 0, $limit) . '…';
    }
}
