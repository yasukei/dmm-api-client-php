<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\DmmApiClient;

/**
 * probe.php のコマンドライン引数。
 */
final readonly class Options
{
    /** 取得するページ位置。 */
    public const array PAGES = ['first', 'middle', 'last'];

    /** 値を取るオプション。 */
    private const array VALUE_OPTIONS = [
        'out', 'run', 'endpoint', 'site', 'service', 'floor', 'sort',
        'pages', 'hits', 'rate', 'limit', 'env-file', 'base-uri',
    ];

    /** 値を取らないオプション。 */
    private const array FLAG_OPTIONS = ['resume', 'revalidate', 'dry-run', 'no-mask', 'help'];

    /**
     * @param list<string> $endpoints 対象のエンドポイント名。空なら全件
     * @param list<string> $sites     対象のサイトコード。空なら全件
     * @param list<string> $services  対象のサービスコード。空なら全件
     * @param list<string> $floors    対象のフロアコード。空なら全件
     * @param list<string> $sorts     対象の sort 値。空なら全件
     * @param list<string> $pages     取得するページ位置
     */
    private function __construct(
        public string $outRoot,
        public ?string $runDir,
        public bool $resume,
        public bool $revalidate,
        public bool $dryRun,
        public bool $mask,
        public array $endpoints,
        public array $sites,
        public array $services,
        public array $floors,
        public array $sorts,
        public array $pages,
        public ?int $hits,
        public float $rate,
        public ?int $limit,
        public ?string $envFile,
        public string $baseUri,
        public bool $help,
    ) {
    }

    /**
     * @param list<string> $argv          スクリプト名を含む引数
     * @param string       $defaultOutRoot `--out` 未指定時の出力ルート
     *
     * @throws ProbeException 引数の書式や値が不正な場合
     */
    public static function parse(array $argv, string $defaultOutRoot): self
    {
        /** @var array<string, string> $values */
        $values = [];
        /** @var array<string, true> $flags */
        $flags = [];

        foreach (array_slice($argv, 1) as $argument) {
            if (! str_starts_with($argument, '--')) {
                throw new ProbeException(sprintf('Unexpected argument "%s". Options must start with "--".', $argument));
            }

            $body = substr($argument, 2);
            $separator = strpos($body, '=');

            if ($separator === false) {
                if (! in_array($body, self::FLAG_OPTIONS, true)) {
                    throw new ProbeException(self::unknownOption($body));
                }

                $flags[$body] = true;

                continue;
            }

            $name = substr($body, 0, $separator);

            if (! in_array($name, self::VALUE_OPTIONS, true)) {
                throw new ProbeException(self::unknownOption($name));
            }

            $values[$name] = substr($body, $separator + 1);
        }

        $pages = self::split($values['pages'] ?? '');

        foreach ($pages as $page) {
            if (! in_array($page, self::PAGES, true)) {
                throw new ProbeException(sprintf(
                    'Invalid --pages value "%s". Expected one of: %s.',
                    $page,
                    implode(', ', self::PAGES),
                ));
            }
        }

        return new self(
            outRoot: $values['out'] ?? $defaultOutRoot,
            runDir: $values['run'] ?? null,
            resume: isset($flags['resume']),
            revalidate: isset($flags['revalidate']),
            dryRun: isset($flags['dry-run']),
            mask: ! isset($flags['no-mask']),
            endpoints: self::endpoints($values['endpoint'] ?? ''),
            sites: self::split($values['site'] ?? ''),
            services: self::split($values['service'] ?? ''),
            floors: self::split($values['floor'] ?? ''),
            sorts: self::split($values['sort'] ?? ''),
            pages: $pages === [] ? self::PAGES : $pages,
            hits: self::positiveInt($values, 'hits'),
            rate: self::rate($values['rate'] ?? null),
            limit: self::positiveInt($values, 'limit'),
            envFile: $values['env-file'] ?? null,
            baseUri: rtrim($values['base-uri'] ?? DmmApiClient::DEFAULT_BASE_URI, '/'),
            help: isset($flags['help']),
        );
    }

    public function wantsEndpoint(string $endpoint): bool
    {
        return $this->endpoints === [] || in_array($endpoint, $this->endpoints, true);
    }

    public function wantsSort(string $sort): bool
    {
        return $this->sorts === [] || in_array($sort, $this->sorts, true);
    }

    /**
     * sort を持たないエンドポイントを対象にするか。
     *
     * `--sort` は絞り込みなので、指定された場合は sort を持たないエンドポイントを外す。
     */
    public function wantsUnsortedEndpoints(): bool
    {
        return $this->sorts === [];
    }

    public function wantsPage(string $page): bool
    {
        return in_array($page, $this->pages, true);
    }

    public function wantsFloor(FloorRef $floor): bool
    {
        return self::matches($this->sites, $floor->site->value)
            && self::matches($this->services, $floor->serviceCode)
            && self::matches($this->floors, $floor->floorCode);
    }

    /**
     * 実際に使う hits。`--hits` はエンドポイントごとの上限で頭打ちにする。
     */
    public function hitsFor(int $max): int
    {
        return $this->hits === null ? $max : min($this->hits, $max);
    }

    /**
     * 実行内容として run.json に残す値。
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'endpoints' => $this->endpoints,
            'sites' => $this->sites,
            'services' => $this->services,
            'floors' => $this->floors,
            'sorts' => $this->sorts,
            'pages' => $this->pages,
            'hits' => $this->hits,
            'rate' => $this->rate,
            'limit' => $this->limit,
            'mask' => $this->mask,
            'resume' => $this->resume,
            'baseUri' => $this->baseUri,
        ];
    }

    /**
     * @param list<string> $allowed
     */
    private static function matches(array $allowed, string $value): bool
    {
        return $allowed === [] || in_array($value, $allowed, true);
    }

    /**
     * エンドポイント名は大文字小文字を無視して受け付け、正規の名前に直す。
     *
     * @return list<string>
     *
     * @throws ProbeException
     */
    private static function endpoints(string $raw): array
    {
        $canonical = [];

        foreach (self::split($raw) as $name) {
            $matched = null;

            foreach (Planner::ENDPOINTS as $endpoint) {
                if (strcasecmp($endpoint, $name) === 0) {
                    $matched = $endpoint;

                    break;
                }
            }

            $canonical[] = $matched ?? throw new ProbeException(sprintf(
                'Unknown --endpoint value "%s". Expected one of: %s.',
                $name,
                implode(', ', Planner::ENDPOINTS),
            ));
        }

        return $canonical;
    }

    /**
     * @param array<string, string> $values
     *
     * @throws ProbeException
     */
    private static function positiveInt(array $values, string $name): ?int
    {
        $value = $values[$name] ?? null;

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d+$/', $value) !== 1 || (int) $value < 1) {
            throw new ProbeException(sprintf('Option "--%s" must be a positive integer, "%s" given.', $name, $value));
        }

        return (int) $value;
    }

    /**
     * @throws ProbeException
     */
    private static function rate(?string $value): float
    {
        if ($value === null) {
            return 1.0;
        }

        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            throw new ProbeException(sprintf('Option "--rate" must be a number, "%s" given.', $value));
        }

        return (float) $value;
    }

    /**
     * @return list<string>
     */
    private static function split(string $raw): array
    {
        $values = [];

        foreach (explode(',', $raw) as $value) {
            $value = trim($value);

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    private static function unknownOption(string $name): string
    {
        return sprintf(
            'Unknown option "--%s". Run with --help to see the available options.',
            $name,
        );
    }
}
