<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Exception\DmmApiClientException;
use DmmApiClient\Exception\InvalidArgumentException;
use DmmApiClient\Exception\UsageException;
use Psr\Http\Client\ClientInterface;

/**
 * `bin/dmm` の入口。サブコマンドを選び、引数を解釈して実行する。
 */
final readonly class Application
{
    public const int EXIT_SUCCESS = 0;

    /** API 呼び出しやレスポンス検証に失敗した。 */
    public const int EXIT_FAILURE = 1;

    /** コマンドラインの指定に誤りがある。 */
    public const int EXIT_USAGE = 2;

    private const string BINARY = 'dmm';

    /** @var list<Command> */
    private array $commands;

    private Output $output;

    /**
     * @param ClientInterface|null $httpClient 送信に使う PSR-18 クライアント。null なら自動検出する
     */
    public function __construct(?ClientInterface $httpClient = null, ?Output $output = null)
    {
        $this->commands = [
            new ItemListCommand($httpClient),
            new FloorListCommand($httpClient),
            new ActressSearchCommand($httpClient),
            new GenreSearchCommand($httpClient),
            new MakerSearchCommand($httpClient),
            new SeriesSearchCommand($httpClient),
            new AuthorSearchCommand($httpClient),
        ];
        $this->output = $output ?? new Output();
    }

    /**
     * @param list<string> $argv
     *
     * @return int 終了コード
     */
    public function run(array $argv): int
    {
        $tokens = array_slice($argv, 1);
        $name = $tokens[0] ?? null;

        if ($name === null || $name === '--help') {
            $this->printApplicationHelp();

            return self::EXIT_SUCCESS;
        }

        $command = $this->find($name);

        if ($command === null) {
            $this->output->error(sprintf('Unknown command "%s".', $name));
            $this->output->error('');
            $this->printApplicationHelp();

            return self::EXIT_USAGE;
        }

        $arguments = array_slice($tokens, 1);

        try {
            $input = Input::parse($arguments, $command->options());

            // ヘルプはパースを通してから判定する。パースの前に引数を走査すると、
            // オプションとして書かれた --help と、オプションの値として書かれた
            // --help を区別できず、値のつもりの指定を横取りしてしまう。
            if ($input->flag('help')) {
                $this->printCommandHelp($command);

                return self::EXIT_SUCCESS;
            }

            return $command->execute($input, $this->loadEnvironment($input), $this->output);
        } catch (UsageException | InvalidArgumentException $exception) {
            $this->output->error($exception->getMessage());
            $this->output->error(sprintf('Run "%s %s --help" for usage.', self::BINARY, $command->name()));

            return self::EXIT_USAGE;
        } catch (DmmApiClientException $exception) {
            $this->output->error($exception->getMessage());

            return self::EXIT_FAILURE;
        }
    }

    private function find(string $name): ?Command
    {
        foreach ($this->commands as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }

        return null;
    }

    private function loadEnvironment(Input $input): Environment
    {
        $path = $input->option('env-file');

        if ($path !== null) {
            return Environment::load($path, required: true);
        }

        $workingDirectory = getcwd();

        return Environment::load($workingDirectory === false ? null : $workingDirectory . '/.env');
    }

    private function printApplicationHelp(): void
    {
        $this->output->line('DMM Web Service API v3 のレスポンスを確認するためのコマンド。');
        $this->output->line();
        $this->output->line(sprintf('使い方: %s <command> [options]', self::BINARY));
        $this->output->line();
        $this->output->line('コマンド:');

        $width = 0;

        foreach ($this->commands as $command) {
            $width = max($width, strlen($command->name()));
        }

        foreach ($this->commands as $command) {
            $this->output->line(sprintf('  %s  %s', str_pad($command->name(), $width), $command->description()));
        }

        $this->output->line();
        $this->output->line('認証情報:');
        $this->output->line('  環境変数 DMM_API_ID と DMM_AFFILIATE_ID、またはカレントディレクトリの .env から読み込む。');
        $this->output->line('  環境変数が .env より優先される。読み込む .env は --env-file で変更できる。');
        $this->output->line();
        $this->output->line(sprintf('各コマンドの詳細は "%s <command> --help" を参照。', self::BINARY));
    }

    private function printCommandHelp(Command $command): void
    {
        $this->output->line($command->description());
        $this->output->line();
        $this->output->line(sprintf('使い方: %s %s [options]', self::BINARY, $command->name()));

        $options = $command->options();
        $width = 0;

        foreach ($options as $option) {
            $width = max($width, strlen($option->label()));
        }

        $this->output->line();
        $this->output->line('オプション:');

        foreach ($options as $option) {
            $this->output->line(sprintf('  %s  %s', str_pad($option->label(), $width), $option->description));
        }
    }
}
