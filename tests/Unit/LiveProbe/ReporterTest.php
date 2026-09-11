<?php

declare(strict_types=1);

use DmmApiClient\Api\Response\ItemList\ItemListResponse;
use DmmApiClient\LiveProbe\Planner;
use DmmApiClient\LiveProbe\Record;
use DmmApiClient\LiveProbe\Reporter;
use DmmApiClient\LiveProbe\RunDirectory;

/**
 * 空の run ディレクトリを一時領域に作る。
 */
function reporterRun(): RunDirectory
{
    $root = sys_get_temp_dir() . '/dmm-reporter-' . bin2hex(random_bytes(6));

    return RunDirectory::create($root, 'run');
}

/**
 * 絞り込みを試した 1 リクエスト分の記録。
 *
 * @param array<string, string> $context
 */
function reporterRecord(string $group, array $context, string $outcome, ?string $file = null): Record
{
    return new Record(
        group: $group,
        endpoint: '/ItemList',
        responseClass: ItemListResponse::class,
        file: $file,
        context: ['floor' => 'videoa', 'floor_id' => '43'] + $context,
        sort: null,
        hits: 100,
        offset: 1,
        page: null,
        uri: 'https://api.dmm.com/affiliate/v3/ItemList',
        outcome: $outcome,
        httpStatus: $outcome === Record::OUTCOME_OK ? 200 : null,
        totalCount: null,
        resultCount: null,
        validation: Record::VALIDATION_SKIPPED,
        errors: [],
        message: $outcome === Record::OUTCOME_TRANSPORT_ERROR ? 'Connection timed out.' : null,
        durationMs: 10,
    );
}

/**
 * `article` の判定行だけを summary から取り出す。
 */
function reporterFilterLine(Reporter $reporter, string $name): string
{
    foreach (explode(PHP_EOL, $reporter->summary()) as $line) {
        if (str_starts_with($line, '  ' . $name . ' ')) {
            return trim($line);
        }
    }

    return '';
}

test('届かなかった article の試行は unreachable として数える', function (): void {
    $reporter = new Reporter(
        [reporterRecord(Planner::ARTICLES, ['article' => 'genre', 'article_id' => '1031'], Record::OUTCOME_TRANSPORT_ERROR)],
        reporterRun(),
        1.0,
    );

    expect(reporterFilterLine($reporter, 'genre'))->toBe('genre                    1  unreachable 1');
});

test('届かなかった mono_stock の試行は unreachable として数える', function (): void {
    $reporter = new Reporter(
        [reporterRecord(Planner::MONO_STOCK, ['mono_stock' => 'stock'], Record::OUTCOME_TRANSPORT_ERROR)],
        reporterRun(),
        1.0,
    );

    expect(reporterFilterLine($reporter, 'stock'))->toBe('stock                    1  unreachable 1');
});

test('届かなかった試行は引き直しとして振り替えない', function (): void {
    $reporter = new Reporter(
        [
            reporterRecord(Planner::ARTICLES, ['article' => 'genre', 'article_id' => '1031'], Record::OUTCOME_TRANSPORT_ERROR),
            reporterRecord(Planner::ARTICLES, ['article' => 'genre', 'article_id' => '4025'], Record::OUTCOME_TRANSPORT_ERROR),
        ],
        reporterRun(),
        1.0,
    );

    expect(reporterFilterLine($reporter, 'genre'))->toBe('genre                    2  unreachable 2');
});

test('0 件で返った試行は empty のまま', function (): void {
    $run = reporterRun();
    $run->save('ItemList/empty.json', json_encode(['result' => ['items' => []]], JSON_THROW_ON_ERROR));

    $reporter = new Reporter(
        [reporterRecord(
            Planner::ARTICLES,
            ['article' => 'genre', 'article_id' => '1031'],
            Record::OUTCOME_OK,
            'ItemList/empty.json',
        )],
        $run,
        1.0,
    );

    expect(reporterFilterLine($reporter, 'genre'))->toBe('genre                    1  empty 1');
});
