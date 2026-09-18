<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\Api\CredentialMasker;
use DmmApiClient\Api\Exception\DmmApiClientException;
use DmmApiClient\Api\Request\ArticleType;
use DmmApiClient\Api\Request\Credentials;
use DmmApiClient\Api\Request\FloorListRequest;
use DmmApiClient\Api\Request\Request;
use DmmApiClient\Api\Response\FloorList\FloorListResponse;
use DmmApiClient\Console\Environment;
use DmmApiClient\Console\Output;
use DmmApiClient\Console\UsageException;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Client\ClientInterface;

/**
 * 実データで DTO の検証を確かめるための取得ツール。
 *
 * `FloorList` で全フロアを取り出し、フロアごとに残りの API を、
 * sort の全種別と先頭・中間・末尾のページで叩く。受け取ったレスポンスは
 * 1 リクエスト 1 ファイルで保存し、DTO へのマッピングに失敗したものを
 * まとめてレポートにする。
 */
final readonly class Probe
{
    private const int EXIT_SUCCESS = 0;

    /** 検証に失敗したリクエストがあった。 */
    private const int EXIT_FAILURE = 1;

    /** 使い方や実行環境に問題がある。 */
    private const int EXIT_USAGE = 2;

    /** `FloorList` の保存先。フロアの一覧はここから読み直せる。 */
    private const string FLOOR_LIST_FILE = 'FloorList/floor-list.json';

    /**
     * @param list<string> $argv
     */
    public static function main(array $argv, string $defaultOutRoot): int
    {
        $console = new Console(new Output());

        try {
            $options = Options::parse($argv, $defaultOutRoot);
        } catch (ProbeException $exception) {
            $console->progress($exception->getMessage());

            return self::EXIT_USAGE;
        }

        if ($options->help) {
            $console->report(self::usage($defaultOutRoot));

            return self::EXIT_SUCCESS;
        }

        try {
            return (new self($options, $console))->run();
        } catch (ProbeException|UsageException|DmmApiClientException $exception) {
            // 取得の途中で起きた失敗はレポートに集約する。ここに来るのは、
            // 認証情報が不正だった、FloorList が引けなかったなど、続けられない場合。
            $console->progress($exception->getMessage());

            return self::EXIT_USAGE;
        }
    }

    private function __construct(
        private Options $options,
        private Console $console,
    ) {}

    /**
     * @throws ProbeException|UsageException|DmmApiClientException
     */
    private function run(): int
    {
        if ($this->options->revalidate) {
            return $this->revalidate();
        }

        $credentials = Environment::loadFor($this->options->envFile)->credentials();
        $masker = $this->options->mask ? CredentialMasker::forCredentials($credentials) : CredentialMasker::disabled();
        $console = new Console((new Output())->masked($masker));
        $clients = new Clients($credentials, self::httpClient(), $this->options->baseUri);

        if ($this->options->dryRun) {
            return $this->dryRun($clients, $credentials, $masker, $console);
        }

        $run = $this->openRunDirectory(create: true);
        $previous = $this->options->resume ? self::index($run) : [];
        $runner = new Runner($clients, $masker, new Validator(), $run, $this->options, $previous);
        $startedAt = date('c');
        $clock = microtime(true);

        $run->writeRun([
            'startedAt' => $startedAt,
            'options' => $this->options->toArray(),
        ]);

        $console->progress(sprintf('run directory: %s', $run->path));

        $floorList = $runner->execute(self::floorListTarget(), 1, null);
        $run->appendRecord($floorList);
        $console->progress(self::progressLine(1, 1, $floorList));

        $catalog = FloorCatalog::fromDecoded(Json::decode($run->read(self::FLOOR_LIST_FILE) ?? '') ?? []);

        foreach ($catalog->warnings as $warning) {
            $console->progress('FloorList: ' . $warning);
        }

        if ($catalog->floors === []) {
            throw new ProbeException('FloorList returned no usable floors; nothing else can be planned.');
        }

        $targets = Planner::build($catalog->floors, $this->options, $credentials);
        $console->progress(sprintf(
            'planned %d targets over %d floors (%d floors matched the filters)',
            count($targets),
            count($catalog->floors),
            count(array_filter($catalog->floors, $this->options->wantsFloor(...))),
        ));

        // 前の段が `--limit` に達したら、その先の段は始めない。
        $this->runTargets($runner, $targets, $console, $run)
            && $this->processArticles($runner, $catalog, $console, $run)
            && $this->processArticleCombos($runner, $catalog, $console, $run)
            && $this->processMonoStock($runner, $catalog, $console, $run);

        $run->writeRun([
            'startedAt' => $startedAt,
            'finishedAt' => date('c'),
            'options' => $this->options->toArray(),
            'requestsSent' => $runner->sent(),
        ]);

        // 取得中は 1 件ずつ追記している。`--resume` では取得し直さなかった分も改めて記録するので、
        // 同じレスポンスの行が並ぶことがある。最後に 1 レスポンス 1 行へまとめ直す。
        $run->writeRecords(RunDirectory::latestPerResponse($run->records()));

        return $this->report($runner->records(), $run, $console, microtime(true) - $clock);
    }

    /**
     * 対象を順に処理する。
     *
     * @param list<Target> $targets
     *
     * @return bool `--limit` に達せず最後まで回ったか
     */
    private function runTargets(
        Runner $runner,
        array $targets,
        Console $console,
        RunDirectory $run,
    ): bool {
        $index = 0;
        $total = count($targets);
        /** @var array<string, true> $onePage 総件数が 1 ページに収まると分かったフロア */
        $onePage = [];

        foreach ($targets as $target) {
            $index++;

            if (self::sortAddsNothing($target, $onePage)) {
                continue;
            }

            $record = $this->processTarget($runner, $target, $index, $total, $console, $run);

            if (self::fitsOnOnePage($target, $record)) {
                $onePage[$target->context['floor_id'] ?? ''] = true;
                $console->progress(sprintf(
                    '%s holds %d items; the other sorts would only reorder them.',
                    $target->key,
                    $record->totalCount ?? 0,
                ));
            }

            if ($runner->limitReached()) {
                $console->progress(sprintf('reached --limit=%d; stopping.', $this->options->limit));

                return false;
            }
        }

        return true;
    }

    /**
     * その対象を叩いても、検証の材料が増えないか。
     *
     * 1 ページに収まるフロアでは、sort を変えても同じ商品が並び替わって返るだけで、
     * DTO に通す形は変わらない。総件数の多いフロアで sort ごとの違いは見られているので、
     * ここで打ち切っても見落としにはならない。
     *
     * @param array<string, true> $onePage
     */
    private static function sortAddsNothing(Target $target, array $onePage): bool
    {
        return $target->group === Planner::ITEM_LIST
            && $target->sort !== null
            && isset($onePage[$target->context['floor_id'] ?? '']);
    }

    /**
     * そのフロアの総件数が、1 ページに収まったか。
     */
    private static function fitsOnOnePage(Target $target, Record $record): bool
    {
        return $target->group === Planner::ITEM_LIST
            && $target->sort !== null
            && $target->hits !== null
            && $record->totalCount !== null
            && $record->totalCount <= $target->hits;
    }

    /**
     * フロアごとに、集まった実データから `article` に指定するものを決めて叩き直す。
     *
     * 何を指定できるかはフロアの中身次第なので、対象は sweep が済むまで決まらない。
     * `ItemList` の処理の最後に続けて行う。切り離して選べるようにはしない。単独では
     * 何を叩けばよいかが決まらず、判定に要る「絞り込み無しの件数」も手元に無いため。
     *
     * 数えるのは保存済みのレスポンスなので、`--resume` なら取得は走らない
     * （`--endpoint=ItemList --resume` で、取得済みの `ItemList` から article だけを試し直せる）。
     *
     * @return bool `--limit` に達せず最後まで回ったか
     */
    private function processArticles(
        Runner $runner,
        FloorCatalog $catalog,
        Console $console,
        RunDirectory $run,
    ): bool {
        if (! $this->wantsFollowUps()) {
            return true;
        }

        $floors = array_values(array_filter($catalog->floors, $this->options->wantsFloor(...)));
        $plans = [];

        foreach ($floors as $floor) {
            $tally = ArticleTally::fromBodies($run->bodies(Planner::ITEM_LIST, $floor->key() . '__'));

            foreach ($tally->articles() as $article => $id) {
                $plans[] = [$floor, $tally, $article, $id];
            }
        }

        if ($plans === []) {
            $console->progress('no article filters to try; no saved ItemList response carries iteminfo.');

            return true;
        }

        $console->progress(sprintf('planned %d article filters over %d floors', count($plans), count($floors)));

        $index = 0;
        $total = count($plans);

        foreach ($plans as [$floor, $tally, $article, $id]) {
            $index++;
            $record = $this->processTarget(
                $runner,
                Planner::articleTarget($floor, $article, $id, $this->options),
                $index,
                $total,
                $console,
                $run,
            );

            // 0 件で返ったら、次に多い ID で 1 度だけ引き直す。最も多い ID が「該当なし」を
            // 表す枠だと、その分類が使えるかどうかが分からないまま終わってしまうため。
            // 2 つ続けて 0 件なら、それはその分類で絞り込めていないという結果として扱える。
            $next = self::drewNothing($record) ? $tally->next($article, $id) : null;

            if ($next !== null) {
                $this->processTarget(
                    $runner,
                    Planner::articleTarget($floor, $article, $next, $this->options),
                    $index,
                    $total,
                    $console,
                    $run,
                );
            }

            if ($runner->limitReached()) {
                $console->progress(sprintf('reached --limit=%d; stopping.', $this->options->limit));

                return false;
            }
        }

        return true;
    }

    /**
     * サイトごとに 1 フロアだけ選び、分類を 2 つ・3 つまとめて指定して叩く。
     *
     * 全フロアでやる必要は無い。1 つだけ指定する場合と違って、分類ごとの可否を見るのではなく、
     * 複数指定そのものが成り立つか（エコーバックの形と、絞り込みが重ねて効くか）を見るため。
     *
     * 選ぶのは、そのサイトで実データに出た分類がいちばん多いフロア。同数なら floor_id の小さい方。
     * 複数指定が最も意味を持つ場所であり、選び方は実行のたびに変わらない。
     *
     * @return bool `--limit` に達せず最後まで回ったか
     */
    private function processArticleCombos(
        Runner $runner,
        FloorCatalog $catalog,
        Console $console,
        RunDirectory $run,
    ): bool {
        if (! $this->wantsFollowUps()) {
            return true;
        }

        $plans = [];

        foreach (self::comboFloors($runner, $catalog) as $floor) {
            $articles = self::articlesOfOneItem($run, $floor);

            foreach ([2, 3] as $count) {
                if (count($articles) >= $count) {
                    $plans[] = Planner::articleComboTarget(
                        $floor,
                        array_slice($articles, 0, $count, preserve_keys: true),
                        $this->options,
                    );
                }
            }
        }

        if ($plans === []) {
            $console->progress('no article combinations to try; no floor carries two articles on one item.');

            return true;
        }

        $console->progress(sprintf('planned %d article combinations', count($plans)));

        return $this->runTargets($runner, $plans, $console, $run);
    }

    /**
     * サイトごとに 1 つずつ、分類の多いフロア。
     *
     * どの分類がそのフロアに出たかは、直前に叩いた article の記録から分かる。
     * 保存済みのレスポンスを数え直す必要は無い。
     *
     * @return list<FloorRef>
     */
    private static function comboFloors(Runner $runner, FloorCatalog $catalog): array
    {
        /** @var array<string, array<string, true>> $articles フロア ID => 分類名 */
        $articles = [];

        foreach ($runner->records() as $record) {
            $article = $record->context['article'] ?? null;
            $floorId = $record->context['floor_id'] ?? null;

            // 複数指定した分の記録は、名前がカンマで連なっている。数え直しの材料にはしない。
            if ($record->group !== Planner::ARTICLES || $article === null || $floorId === null) {
                continue;
            }

            if (! str_contains($article, ',') && ArticleType::tryFrom($article) !== null) {
                $articles[$floorId][$article] = true;
            }
        }

        $best = [];

        foreach ($catalog->floors as $floor) {
            $count = count($articles[$floor->floorId] ?? []);
            $site = $floor->site;

            if ($count >= 2 && self::beats($count, $floor, $best[$site] ?? null)) {
                $best[$site] = [$count, $floor];
            }
        }

        ksort($best);

        return array_values(array_map(static fn(array $pair): FloorRef => $pair[1], $best));
    }

    /**
     * そのフロアが、今のところ選ばれているものより相応しいか。
     *
     * 分類の多い方を選ぶ。同数なら floor_id の小さい方にする。`FloorList` に現れた順に任せると、
     * DMM 側の並びが変わっただけで選ばれるフロアが動いてしまう。
     *
     * @param array{int, FloorRef}|null $current
     */
    private static function beats(int $count, FloorRef $floor, ?array $current): bool
    {
        if ($current === null) {
            return true;
        }

        [$bestCount, $best] = $current;

        return $count > $bestCount
            || ($count === $bestCount && self::floorOrder($floor) < self::floorOrder($best));
    }

    /**
     * floor_id を並べ替えのための値にする。
     *
     * 数値で返ってくるが型は文字列なので、数として比べる。数にならない値が現れたら、
     * 他のどれよりうしろに置いて、選択が壊れないようにする。
     */
    private static function floorOrder(FloorRef $floor): int
    {
        return preg_match('/^\d+$/', $floor->floorId) === 1 ? (int) $floor->floorId : PHP_INT_MAX;
    }

    /**
     * そのフロアの商品 1 件が持つ、分類と ID。
     *
     * 分類ごとに最も多い ID を別々に選ぶと、重ねたときに該当なしで返りかねない。1 件の商品が
     * 実際に持っている組み合わせを使えば、少なくともその商品は必ず当たる。
     *
     * 保存済みのレスポンスを順に見て、いちばん多くの分類を持つ商品を選ぶ。3 つ持つ商品が
     * 見つかった時点で打ち切る。指定するのは最大 3 つなので、それ以上は見なくてよい。
     *
     * @return array<string, string> 分類名 => ID。名前順
     */
    private static function articlesOfOneItem(RunDirectory $run, FloorRef $floor): array
    {
        $best = [];

        foreach ($run->bodies(Planner::ITEM_LIST, $floor->key() . '__') as $body) {
            foreach (ArticleTally::itemsOf(Json::decode($body)) as $item) {
                $articles = [];

                foreach (ArticleTally::articlesOf($item) as $article => $ids) {
                    if ($ids !== [] && ArticleType::tryFrom($article) !== null) {
                        $articles[$article] = $ids[0];
                    }
                }

                if (count($articles) > count($best)) {
                    ksort($articles);
                    $best = $articles;
                }

                if (count($best) >= 3) {
                    return $best;
                }
            }
        }

        return $best;
    }

    /**
     * 通販のフロアを、`mono_stock` の値ごとに叩き直す。
     *
     * どの値で絞り込めるかは、`article` と同じく実際に叩かないと分からない。
     * 絞り込みに使えないと分かっている値も送る。そう書いてあるだけで、
     * 全フロアで裏を取ったわけではないため。
     *
     * @return bool `--limit` に達せず最後まで回ったか
     */
    private function processMonoStock(
        Runner $runner,
        FloorCatalog $catalog,
        Console $console,
        RunDirectory $run,
    ): bool {
        if (! $this->wantsFollowUps()) {
            return true;
        }

        $targets = [];

        foreach ($catalog->floors as $floor) {
            if ($this->options->wantsFloor($floor)) {
                $targets = [...$targets, ...Planner::monoStockTargets($floor, $this->options)];
            }
        }

        if ($targets === []) {
            $console->progress('no mono_stock filters to try; no floor matched the mono service.');

            return true;
        }

        $console->progress(sprintf('planned %d mono_stock filters', count($targets)));

        return $this->runTargets($runner, $targets, $console, $run);
    }

    /**
     * `ItemList` の sweep のあとに続ける対象（article と mono_stock）を回すか。
     *
     * どちらも `ItemList` の一部なので `--endpoint` では選べない。sort を持たないので、
     * `--sort` で絞られた回は sort を持たない他の API と同じく対象から外す。
     */
    private function wantsFollowUps(): bool
    {
        return $this->options->wantsEndpoint(Planner::ITEM_LIST) && $this->options->wantsUnsortedEndpoints();
    }

    /**
     * 取得できたが 1 件も返らなかったか。
     */
    private static function drewNothing(Record $record): bool
    {
        return $record->outcome === Record::OUTCOME_OK
            && ($record->resultCount === 0 || $record->totalCount === 0);
    }

    /**
     * 1 つの対象について、先頭・中間・末尾のページを順に処理する。
     *
     * 総件数は先頭ページを取るまで分からないので、先頭ページは `--pages` の指定によらず必ず取得する。
     *
     * @return Record 先頭ページの記録
     */
    private function processTarget(
        Runner $runner,
        Target $target,
        int $index,
        int $total,
        Console $console,
        RunDirectory $run,
    ): Record {
        $first = $runner->execute($target, 1, $target->isSingle() ? null : 'first');
        $run->appendRecord($first);
        $console->progress(self::progressLine($index, $total, $first));

        if ($target->isSingle()) {
            return $first;
        }

        $seen = [1 => true];

        foreach (self::followUpOffsets($target, $first->totalCount, $this->options) as $page => $offset) {
            if (isset($seen[$offset])) {
                continue;
            }

            $seen[$offset] = true;
            $record = $runner->execute($target, $offset, $page);
            $run->appendRecord($record);
            $console->progress(self::progressLine($index, $total, $record));
        }

        return $first;
    }

    /**
     * 総件数から、中間と末尾のページ位置を決める。
     *
     * @return array<string, int> ページ位置の名前 => offset
     */
    private static function followUpOffsets(Target $target, ?int $totalCount, Options $options): array
    {
        if ($target->firstPageOnly || $totalCount === null || $target->hits === null || $totalCount <= $target->hits) {
            return [];
        }

        $offsets = [];

        if ($options->wantsPage('middle')) {
            $offsets['middle'] = self::clamp(intdiv($totalCount, 2), $target->offsetMax);
        }

        if ($options->wantsPage('last')) {
            $offsets['last'] = self::clamp($totalCount - $target->hits + 1, $target->offsetMax);
        }

        return $offsets;
    }

    private static function clamp(int $value, int $max): int
    {
        return max(1, min($value, $max));
    }

    /**
     * 保存済みのレスポンスを検証し直し、レポートだけを作り直す。
     *
     * @throws ProbeException
     */
    private function revalidate(): int
    {
        $clock = microtime(true);
        $run = $this->openRunDirectory(create: false);
        $validator = new Validator();
        $records = [];

        foreach (RunDirectory::latestPerResponse($run->records()) as $record) {
            // エラーを引くつもりで成功してしまった記録は、検証し直しても言うことが変わらない。
            // 保存されているのは成功したレスポンスで、エラー用の DTO と突き合わせても意味が無い。
            if ($record->file === null || $record->outcome === Record::OUTCOME_UNEXPECTED_OK) {
                $records[] = $record;

                continue;
            }

            $body = $run->read($record->file);

            if ($body === null) {
                $records[] = $record->withValidation(
                    Record::VALIDATION_SKIPPED,
                    [['path' => '*file*', 'message' => 'Saved response is missing.']],
                    [],
                );

                continue;
            }

            $errors = $validator->validate($record->responseClass, $body);
            $records[] = $record->withValidation(
                $errors === [] ? Record::VALIDATION_OK : Record::VALIDATION_FAILED,
                $errors,
                $validator->unknownKeys($record->responseClass, $body),
            );
        }

        $run->writeRecords($records);
        $this->console->progress(sprintf('revalidated %d saved responses in %s', count($records), $run->path));

        return $this->report($records, $run, $this->console, microtime(true) - $clock);
    }

    /**
     * 送信せずに、叩く予定の先頭ページの URI を並べる。
     *
     * フロアの一覧が要るので、過去の実行が残っていればそれを読み、無ければ `FloorList` だけ 1 回叩く。
     *
     * @throws ProbeException
     */
    private function dryRun(
        Clients $clients,
        Credentials $credentials,
        CredentialMasker $masker,
        Console $console,
    ): int {
        $body = $this->savedFloorList();

        if ($body === null) {
            $console->progress('No saved FloorList found; fetching it once.');
            $body = $masker->mask($clients->primary()->fetchRaw(new FloorListRequest()));
        }

        $catalog = FloorCatalog::fromDecoded(Json::decode($body) ?? []);
        $targets = Planner::build($catalog->floors, $this->options, $credentials);

        $console->report($clients->primary()->buildUri(new FloorListRequest()) . PHP_EOL);

        foreach ($targets as $target) {
            $console->report($masker->mask($clients->forTarget($target)->buildUri($target->request(1))) . PHP_EOL);
        }

        $console->progress(sprintf(
            '%d targets planned (first page only is shown; middle/last depend on total_count).',
            count($targets),
        ));
        $console->progress('The article filters ItemList ends with are not listed; '
            . 'what they send is decided from the responses.');

        return self::EXIT_SUCCESS;
    }

    /**
     * 過去の実行から `FloorList` のレスポンスを読む。
     */
    private function savedFloorList(): ?string
    {
        $run = $this->options->runDir !== null
            ? RunDirectory::open($this->options->runDir)
            : RunDirectory::latest($this->options->outRoot);

        return $run?->has(self::FLOOR_LIST_FILE) === true ? $run->read(self::FLOOR_LIST_FILE) : null;
    }

    /**
     * @param list<Record> $records
     * @param float        $elapsed この実行にかかった秒数
     */
    private function report(array $records, RunDirectory $run, Console $console, float $elapsed): int
    {
        $reporter = new Reporter($records, $run, $elapsed);
        $reporter->writeFailures();
        $console->report($reporter->summary());

        return $reporter->hasFailures() ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
    }

    /**
     * 出力先の run ディレクトリを決める。
     *
     * `--run` があればそれを、`--resume` / `--revalidate` なら最新の実行を使う。
     * それ以外は日時を名前にした新しいディレクトリを作る。
     *
     * @throws ProbeException
     */
    private function openRunDirectory(bool $create): RunDirectory
    {
        if ($this->options->runDir !== null) {
            return RunDirectory::open($this->options->runDir);
        }

        if ($this->options->resume || $this->options->revalidate) {
            return RunDirectory::latest($this->options->outRoot) ?? throw new ProbeException(sprintf(
                'No previous run found under "%s". Specify one with --run=PATH.',
                $this->options->outRoot,
            ));
        }

        if (! $create) {
            throw new ProbeException('Specify the run directory with --run=PATH.');
        }

        return RunDirectory::create($this->options->outRoot, date('Ymd-His'));
    }

    /**
     * 保存済みのファイルから、前回の記録を引ける形にする。
     *
     * @return array<string, Record>
     */
    private static function index(RunDirectory $run): array
    {
        if (! $run->has(RunDirectory::MANIFEST)) {
            return [];
        }

        $index = [];

        foreach ($run->records() as $record) {
            if ($record->file !== null) {
                $index[$record->file] = $record;
            }
        }

        return $index;
    }

    /**
     * 長時間止まらないよう、タイムアウトを設定した HTTP クライアントを使う。
     */
    private static function httpClient(): ?ClientInterface
    {
        if (! class_exists(GuzzleClient::class)) {
            return null;
        }

        return new GuzzleClient(['timeout' => 30, 'connect_timeout' => 10]);
    }

    private static function floorListTarget(): Target
    {
        return new Target(
            group: 'FloorList',
            endpoint: FloorListRequest::ENDPOINT,
            responseClass: FloorListResponse::class,
            key: 'floor-list',
            sort: null,
            hits: null,
            offsetMax: 1,
            context: [],
            build: static fn(int $offset): Request => new FloorListRequest(),
        );
    }

    private static function progressLine(int $index, int $total, Record $record): string
    {
        return sprintf(
            '[%d/%d] %-70s %-16s %9s  %s',
            $index,
            $total,
            $record->label(),
            $record->outcome,
            // 取得していない対象に所要時間は無い。0.0 sec と出すと速かったように読めてしまう。
            $record->cached ? '-' : sprintf('%.1f sec', $record->durationMs / 1000),
            $record->validation === Record::VALIDATION_FAILED
                ? sprintf('VALIDATION FAILED (%d)', count($record->errors))
                : sprintf('total=%s', $record->totalCount === null ? '-' : (string) $record->totalCount),
        );
    }

    private static function usage(string $defaultOutRoot): string
    {
        return <<<TEXT
            Usage: php tools/live-probe/probe.php [options]

            FloorList で全フロアを取り出し、フロアごとに各 API を sort の全種別・
            先頭/中間/末尾のページで叩いて、レスポンスの保存と DTO 検証を行う。
            ItemList の最後には、フロアごとに実データへ出た分類を article / article_id に
            指定して叩き直す。最後に、わざとエラーを引くリクエスト（--endpoint=Errors）を
            送り、エラー用の DTO も実データで検証する。

            保存先:
              {$defaultOutRoot}/<日時>/
                FloorList/floor-list.json
                ItemList/FANZA__digital__videoa-43__sort-date__hits-100__offset-000001.json
                manifest.jsonl   1 リクエスト 1 行の記録
                run.json         実行条件と集計
                failures.json    検証に失敗した箇所の一覧
                failures.md      同上（読む用）

            Options:
              --out=PATH        出力ルート（既定: {$defaultOutRoot}）
              --run=PATH        対象にする実行ディレクトリ（--resume / --revalidate 用）
              --resume          保存済みのファイルは取得し直さず、続きから取得する
              --revalidate      取得せず、保存済みのレスポンスを検証し直してレポートを作り直す
              --dry-run         送信せず、叩く予定の URI を並べる
              --endpoint=A,B    対象の API（既定: 全部。FloorList は常に取得する）
                                Errors は API 名ではなく、わざとエラーを引く対象を指す
              --site=CODE,...   対象のサイトコード（DMM.com, FANZA）
              --service=CODE,.. 対象のサービスコード（digital, mono, ...）
              --floor=CODE,...  対象のフロアコード（videoa, dvd, ...）
              --sort=V,...      対象の sort 値。指定すると sort を持たない API は対象外になる
              --pages=P,...     取得するページ位置（first, middle, last）。
                                総件数を知るため、先頭ページは指定によらず必ず取得する
              --hits=N          hits の上書き（API ごとの上限で頭打ちにする）
              --rate=N          1 秒あたりのリクエスト数（既定: 1。0 で待たない）
              --limit=N         送信するリクエストの上限（試し打ち用。同じ条件のページは
                                まとめて取るので、その分だけ超えることがある）
              --no-mask         認証情報を伏せ字にせず保存する
              --env-file=PATH   読み込む .env（既定: カレントディレクトリの .env）
              --base-uri=URI    API のベース URI（既定: 本番。差し替えるのは動作確認用）
              --help            この使い方を表示する

            Examples:
              php tools/live-probe/probe.php --floor=videoa --endpoint=ItemList --pages=first
              php tools/live-probe/probe.php --endpoint=Errors
              php tools/live-probe/probe.php --endpoint=ItemList --resume  # article だけ試し直す
              php tools/live-probe/probe.php --revalidate
              php tools/live-probe/probe.php --revalidate --run={$defaultOutRoot}/20260904-120000

            TEXT;
    }
}
