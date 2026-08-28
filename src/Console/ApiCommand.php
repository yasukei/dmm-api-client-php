<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use BackedEnum;
use DateTimeImmutable;
use DmmApiClient\CredentialMasker;
use DmmApiClient\DmmApiClient;
use DmmApiClient\Exception\ApiErrorException;
use DmmApiClient\Exception\ResponseValidationException;
use DmmApiClient\Exception\TransportException;
use DmmApiClient\Exception\UsageException;
use DmmApiClient\Request\Credentials;
use DmmApiClient\Request\RawRequest;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\ResponseMapper;
use JsonException;
use Psr\Http\Client\ClientInterface;

/**
 * API を 1 つ呼び出して、そのレスポンスを標準出力へ書き出すサブコマンドの共通部分。
 *
 * 認証情報の解決、オプションの型変換、出力の整形、検証、エラーの扱いはここで完結させ、
 * 個々のコマンドはリクエストの組み立てとオプションの定義だけを受け持つ。
 */
abstract class ApiCommand implements Command
{
    /** `--gte-date` などが受け付ける日時の書式。 */
    private const array DATE_FORMATS = ['!Y-m-d\TH:i:s', '!Y-m-d H:i:s', '!Y-m-d'];

    private readonly ResponseMapper $responseMapper;

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
        ?ResponseMapper $responseMapper = null,
    ) {
        $this->responseMapper = $responseMapper ?? new ResponseMapper();
    }

    /**
     * すべてのサブコマンドが受け付けるオプション。
     *
     * @return list<OptionDefinition>
     */
    final public static function commonOptions(): array
    {
        return [
            new OptionDefinition('env-file', '読み込む .env のパス（既定: カレントディレクトリの .env）', 'PATH'),
            new OptionDefinition('dry-run', '送信せずに、組み立てた URI だけを表示する'),
            new OptionDefinition('raw', 'レスポンスを整形せず、受け取ったまま出力する'),
            new OptionDefinition('no-validate-request', 'パラメータを検証せず、指定した値をそのまま送る'),
            new OptionDefinition('no-validate-response', 'レスポンスの DTO 検証を行わない'),
            new OptionDefinition('no-mask', '認証情報を伏せ字にせず、そのまま出力する'),
            new OptionDefinition('help', 'このコマンドの使い方を表示する'),
        ];
    }

    /**
     * 列挙型が受け付ける値を、ヘルプに載せる形で並べる。
     *
     * @param class-string<BackedEnum> $enum
     */
    final public static function allowedValues(string $enum): string
    {
        return implode(', ', array_map(
            static fn (BackedEnum $case): string => (string) $case->value,
            $enum::cases(),
        ));
    }

    /**
     * このコマンド固有のオプション。
     *
     * @return list<OptionDefinition>
     */
    abstract protected function requestOptions(): array;

    /**
     * 呼び出す API のエンドポイントパス（例: `/FloorList`）。
     */
    abstract protected function endpoint(): string;

    /**
     * オプションからリクエストを組み立てる。
     *
     * @throws UsageException オプションの値が不正な場合
     */
    abstract protected function createRequest(Input $input): Request;

    /**
     * レスポンスの検証に使う DTO。
     *
     * @return class-string
     */
    abstract protected function responseClass(): string;

    final public function options(): array
    {
        return [...$this->requestOptions(), ...self::commonOptions()];
    }

    final public function execute(Input $input, Environment $environment, Output $output): int
    {
        $unchecked = $input->flag('no-validate-request');
        $credentials = $this->resolveCredentials($environment, $unchecked);

        // 認証情報はエコーバックにも affiliateURL にも埋め込まれて返ってくる。
        // 出力を保存したときに漏れないよう、既定で伏せ字にする。
        $masker = $input->flag('no-mask')
            ? CredentialMasker::disabled()
            : CredentialMasker::forCredentials($credentials);

        $client = new DmmApiClient($credentials, $this->httpClient);
        $request = $unchecked ? $this->createUncheckedRequest($input) : $this->createRequest($input);

        if ($input->flag('dry-run')) {
            $output->line($masker->mask($client->buildUri($request)));

            return Application::EXIT_SUCCESS;
        }

        try {
            $body = $client->fetchRaw($request);
        } catch (ApiErrorException $exception) {
            // エラーの中身こそ見たいので、ボディは通常どおり標準出力へ流す。
            $output->write($masker->mask($this->format($exception->responseBody, $input, $output)));
            $output->error($masker->mask($exception->getMessage()));

            return Application::EXIT_FAILURE;
        } catch (TransportException $exception) {
            $output->error($masker->mask($exception->getMessage()));

            return Application::EXIT_FAILURE;
        }

        $output->write($masker->mask($this->format($body, $input, $output)));

        if ($input->flag('no-validate-response')) {
            return Application::EXIT_SUCCESS;
        }

        // 検証は、伏せ字にする前の実際のレスポンスに対して行う。
        return $this->validate($body, $output);
    }

    /**
     * 必ず指定されていなければならないオプションの値。
     *
     * @throws UsageException 指定されていない場合
     */
    final protected function requiredOption(Input $input, string $name): string
    {
        return $input->option($name) ?? throw new UsageException(sprintf('Option "--%s" is required.', $name));
    }

    /**
     * 整数として解釈したオプションの値。
     *
     * @throws UsageException 整数として読めない場合
     */
    final protected function intOption(Input $input, string $name): ?int
    {
        $value = $input->option($name);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^-?\d+$/', $value) !== 1) {
            throw new UsageException(sprintf('Option "--%s" must be an integer, "%s" given.', $name, $value));
        }

        return (int) $value;
    }

    /**
     * 列挙型として解釈したオプションの値。
     *
     * @template T of BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T|null
     *
     * @throws UsageException 列挙型が受け付けない値の場合
     */
    final protected function enumOption(Input $input, string $name, string $enum): ?BackedEnum
    {
        $value = $input->option($name);

        if ($value === null) {
            return null;
        }

        return $enum::tryFrom($value) ?? throw new UsageException(sprintf(
            'Invalid value "%s" for --%s. Expected one of: %s.',
            $value,
            $name,
            self::allowedValues($enum),
        ));
    }

    /**
     * 日時として解釈したオプションの値。
     *
     * @throws UsageException 対応する書式で読めない場合
     */
    final protected function dateOption(Input $input, string $name): ?DateTimeImmutable
    {
        $value = $input->option($name);

        if ($value === null) {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);

            if ($date !== false) {
                return $date;
            }
        }

        throw new UsageException(sprintf(
            'Option "--%s" must be a date like 2016-04-01 or 2016-04-01T00:00:00, "%s" given.',
            $name,
            $value,
        ));
    }

    /**
     * 認証情報を環境変数か `.env` から読み出す。
     *
     * コマンドライン引数からは受け取らない。引数は ps などから他のユーザーにも見え、
     * シェルの履歴にも残るため、認証情報の渡し方として適さない。
     *
     * @param bool $unchecked true の場合、アフィリエイト ID の形式を検証しない
     *
     * @throws UsageException 認証情報が揃わない場合
     */
    private function resolveCredentials(Environment $environment, bool $unchecked): Credentials
    {
        $apiId = $environment->get('DMM_API_ID');
        $affiliateId = $environment->get('DMM_AFFILIATE_ID');

        $missing = [];

        if ($apiId === null) {
            $missing[] = 'DMM_API_ID';
        }

        if ($affiliateId === null) {
            $missing[] = 'DMM_AFFILIATE_ID';
        }

        if ($apiId === null || $affiliateId === null) {
            throw new UsageException(sprintf(
                'Missing credentials: %s. Set them as environment variables, or put them in a .env file.',
                implode(', ', $missing),
            ));
        }

        return $unchecked
            ? Credentials::unchecked($apiId, $affiliateId)
            : new Credentials($apiId, $affiliateId);
    }

    /**
     * 検証を通さずにリクエストを組み立てる。
     *
     * 値を取るオプションのうち指定されたものを、そのままクエリパラメータにする。
     * オプション名の `-` はクエリのキーでは `_` になる（`--gte-date` → `gte_date`）。
     * 繰り返し指定されたオプションは、そのまま複数の値として送る。
     */
    private function createUncheckedRequest(Input $input): Request
    {
        $parameters = [];

        foreach ($this->requestOptions() as $option) {
            if (! $option->takesValue()) {
                continue;
            }

            $values = $input->optionValues($option->name);

            if ($values === []) {
                continue;
            }

            $parameters[str_replace('-', '_', $option->name)] = count($values) === 1 ? $values[0] : $values;
        }

        return new RawRequest($this->endpoint(), $parameters);
    }

    /**
     * `--raw` が無ければ JSON を読みやすく整形する。整形できない場合は受け取ったまま出す。
     */
    private function format(string $body, Input $input, Output $output): string
    {
        if ($input->flag('raw')) {
            return $body;
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $output->error('Could not pretty-print the response: ' . $exception->getMessage());

            return $body;
        }

        return json_encode(
            $decoded,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;
    }

    /**
     * レスポンスが DTO と一致するか確かめ、食い違いを標準エラー出力へ書き出す。
     */
    private function validate(string $body, Output $output): int
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $output->error('Response is not valid JSON: ' . $exception->getMessage());

            return Application::EXIT_FAILURE;
        }

        try {
            $this->responseMapper->map($this->responseClass(), $decoded);
        } catch (ResponseValidationException $exception) {
            $output->error(sprintf('Response did not match %s:', $exception->targetClass));

            foreach ($exception->errors as $error) {
                $output->error(sprintf('  %s: %s', $error['path'], $error['message']));
            }

            return Application::EXIT_FAILURE;
        }

        return Application::EXIT_SUCCESS;
    }
}
