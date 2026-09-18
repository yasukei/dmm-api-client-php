<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use BackedEnum;
use DateTimeImmutable;
use DmmApiClient\Api\CredentialMasker;
use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Exception\ApiErrorException;
use DmmApiClient\Api\Exception\InvalidArgumentException;
use DmmApiClient\Api\Exception\MalformedResponseException;
use DmmApiClient\Api\Exception\ResponseValidationException;
use DmmApiClient\Api\Exception\TransportException;
use DmmApiClient\Api\Request\Credentials;
use DmmApiClient\Api\Request\Request;
use Http\Discovery\Exception\NotFoundException;
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
    /**
     * `--gte-date` などが受け付ける日時の書式。
     *
     * 読み取りと書き戻しの両方に使うため、`!` を含めずに持つ（{@see self::dateOption()}）。
     */
    private const array DATE_FORMATS = ['Y-m-d\TH:i:s', 'Y-m-d H:i:s', self::DATE_ONLY_FORMAT];

    /** 時刻を伴わない書式。省略された時刻をどう埋めるかは、この書式に一致したかで決まる。 */
    private const string DATE_ONLY_FORMAT = 'Y-m-d';

    /**
     * 日付だけを取るオプションの、ヘルプ上の値の表記（例: `--gte-birthday=DATE`）。
     *
     * 生年月日のように、API へ `Y-m-d` で送る値に使う。
     */
    public const string DATE_PLACEHOLDER = 'DATE';

    /**
     * 日付と時刻を取るオプションの、ヘルプ上の値の表記（例: `--gte-date=DATETIME`）。
     *
     * API へ `Y-m-d\TH:i:s` で送る値に使う。時刻を省略して書けるため、
     * 何時何分として扱われるかをヘルプで補う必要がある。
     */
    public const string DATETIME_PLACEHOLDER = 'DATETIME';

    public function __construct(
        private readonly ?ClientInterface $httpClient = null,
    ) {
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
     * 値の表記ごとに、受け付ける書式をヘルプで補う文言。
     *
     * 書式を決めているのは {@see self::dateOption()} なので、文言もここに置く。
     * 時刻を省略したときに何時何分として読むかは `$endOfDay` の指定しだいで、
     * それを知っているのはこのクラスだけ。並べ方は {@see Application} が決める。
     *
     * @return array<string, list<string>>
     */
    final public static function placeholderNotes(): array
    {
        return [
            self::DATETIME_PLACEHOLDER => [
                'DATETIME は 2016-04-01、2016-04-01T12:34:56、2016-04-01 12:34:56 のいずれかで指定する。',
                '時刻を省略した場合、--gte-date は 00:00:00、--lte-date は 23:59:59 として扱う。',
                '空白を含む形はシェルの引用符が要る。',
            ],
            self::DATE_PLACEHOLDER => [
                'DATE は 1990-01-01 のように日付で指定する。時刻は送信しない。',
            ],
        ];
    }

    /**
     * このコマンド固有のオプション。
     *
     * @return list<OptionDefinition>
     */
    abstract protected function requestOptions(): array;

    /**
     * オプションからリクエストを組み立てる。
     *
     * @throws UsageException           オプションの値が不正な場合
     * @throws InvalidArgumentException リクエストが受け付けない値だった場合
     */
    abstract protected function createRequest(Input $input): Request;

    /**
     * 組み立てたリクエストで API を呼び出す。
     *
     * 呼ぶのは {@see DmmApiClient} の型付きメソッドで、検証もマッピングもそちらが行う。
     * 戻り値の DTO はコンソールでは使わない（出力は生ボディから作る）が、
     * 型付きメソッドを通すこと自体が検証を意味する。
     *
     * @throws UsageException               オプションの値が不正な場合
     * @throws InvalidArgumentException     リクエストが受け付けない値だった場合
     * @throws TransportException           HTTP 通信に失敗した場合
     * @throws ApiErrorException            API がエラーを返した場合
     * @throws MalformedResponseException   レスポンスが JSON として読めなかった場合
     * @throws ResponseValidationException  レスポンスが期待する構造と一致しなかった場合
     */
    abstract protected function invoke(DmmApiClient $client, Input $input): object;

    final public function options(): array
    {
        return [...$this->requestOptions(), ...self::commonOptions()];
    }

    /**
     * API の呼び出しで起きた例外はここで終了コードに変える。抜けるのは実行前の段階、
     * つまりオプションや実行環境が整っていない場合だけ。
     *
     * @throws UsageException           オプションの値や実行環境が不正な場合
     * @throws InvalidArgumentException リクエストが受け付けない値だった場合
     */
    final public function execute(Input $input, Environment $environment, Output $output): int
    {
        $credentials = $environment->credentials();

        // 認証情報はエコーバックにも affiliateURL にも埋め込まれて返ってくる。
        // 出力を保存したときに漏れないよう、既定で伏せ字にする。
        // 以降の書き出しはすべてこの $output を通すので、個別に伏せ字にする必要はない。
        $output = $output->masked($input->flag('no-mask')
            ? CredentialMasker::disabled()
            : CredentialMasker::forCredentials($credentials));

        $client = $this->createClient($credentials);

        if ($input->flag('dry-run')) {
            $output->line($client->buildUri($this->createRequest($input)));

            return Application::EXIT_SUCCESS;
        }

        try {
            $this->invoke($client, $input);
        } catch (TransportException $exception) {
            // レスポンスそのものが届いていないので、書き出す本文も無い。
            $output->error($exception->getMessage());

            return Application::EXIT_FAILURE;
        } catch (ApiErrorException | MalformedResponseException $exception) {
            // エラーの中身こそ見たいので、ボディは通常どおり標準出力へ流す。
            $this->writeBody($client, $input, $output);
            $output->error($exception->getMessage());

            return Application::EXIT_FAILURE;
        } catch (ResponseValidationException $exception) {
            $this->writeBody($client, $input, $output);
            $this->reportValidationErrors($exception, $output);

            return Application::EXIT_FAILURE;
        }

        $this->writeBody($client, $input, $output);

        return Application::EXIT_SUCCESS;
    }

    /**
     * 受け取った生ボディを標準出力へ書き出す。
     *
     * 成功しても失敗しても、返ってきたものは見せる。DTO ではなく生ボディを出すのは、
     * DTO が知らないキーを落とさずに、API が返したとおりを見せるため。
     */
    private function writeBody(DmmApiClient $client, Input $input, Output $output): void
    {
        $output->write($this->format($client->lastResponseBody() ?? '', $input, $output));
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

        return $value === null ? null : self::toEnum($value, $name, $enum);
    }

    /**
     * 文字列を列挙型として解釈する。
     *
     * 必須のオプションや、繰り返し指定できるオプションのように
     * {@see self::enumOption()} の形に収まらない場合はこちらを直接使う。
     * 受け付けない値のときの文言を 1 か所に保つためのもの。
     *
     * @template T of BackedEnum
     *
     * @param string          $name  文言に載せるオプション名（`--` は付けない）
     * @param class-string<T> $enum
     *
     * @return T
     *
     * @throws UsageException 列挙型が受け付けない値の場合
     */
    final protected static function toEnum(string $value, string $name, string $enum): BackedEnum
    {
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
     * @param bool $endOfDay 時刻を伴わない値を、その日の終わり（23:59:59）として読む。
     *                       上限を表すオプションに使う。`--lte-date=2016-04-01` を
     *                       00:00:00 と読むと、その日の商品がまるごと外れてしまうため
     *
     * @throws UsageException 対応する書式で読めない場合、または存在しない日付の場合
     */
    final protected function dateOption(Input $input, string $name, bool $endOfDay = false): ?DateTimeImmutable
    {
        $value = $input->option($name);

        if ($value === null) {
            return null;
        }

        $padded = self::padDate($value);

        foreach (self::DATE_FORMATS as $format) {
            // 先頭の `!` は、書式に含まれない要素（日付だけの場合の時刻など）を
            // 現在時刻ではなくゼロで埋めるための指定。
            $date = DateTimeImmutable::createFromFormat('!' . $format, $padded);

            // createFromFormat は範囲外の値を切り上げて受け付ける（2016-04-31 → 2016-05-01）。
            // 打ち間違いが別の日付として通ってしまわないよう、同じ書式へ戻して入力と突き合わせ、
            // 一致した場合だけ採用する。切り上げが起きていれば別の日付になるので一致しない。
            //
            // 切り上げは getLastErrors() の警告としても取れるが、あの窓口は書式違いや
            // 区切り文字の欠落も同じ場所から返すうえ、直近 1 回分の状態を
            // DateTime と共有するグローバルな枠に置く。ここでは書式だけを見れば足りる。
            if ($date === false || $date->format($format) !== $padded) {
                continue;
            }

            return $endOfDay && $format === self::DATE_ONLY_FORMAT
                ? $date->setTime(23, 59, 59)
                : $date;
        }

        throw new UsageException(sprintf(
            'Option "--%s" must be a date like 2016-04-01 or 2016-04-01T00:00:00, "%s" given.',
            $name,
            $value,
        ));
    }

    /**
     * 日付部分の年・月・日を、書式が求める桁数までゼロで埋める。
     *
     * `2016-4-1` のように桁を詰めた書き方は日付では珍しくないため受け付ける。
     * 往復比較は書き戻した文字列と突き合わせるので、埋めておかないと桁数の差だけで弾かれる。
     *
     * 時刻には同じ扱いをしない。分と秒は 2 桁で書くのが通例で、`1:2:3` のような表記は
     * まず使われないため、受け付ける形を広げる理由がない。
     */
    private static function padDate(string $value): string
    {
        return preg_replace_callback(
            '/^(\d{1,4})-(\d{1,2})-(\d{1,2})(?=$|[T ])/',
            static fn (array $matches): string => sprintf(
                '%04d-%02d-%02d',
                $matches[1],
                $matches[2],
                $matches[3],
            ),
            $value,
        ) ?? $value;
    }

    /**
     * クライアントを組み立てる。
     *
     * PSR-18 / PSR-17 の実装は自動検出に任せている。composer.json が要求するのはインタフェースだけで、
     * 実装は利用者が選ぶものなので、このパッケージを依存に入れただけの環境には実装が無いことがある。
     * 検出に失敗したら、認証情報が揃わない場合と同じく実行環境の問題として扱う。素通しすると
     * {@see Application::run()} のどの catch にも当たらず、スタックトレースのまま終わってしまう。
     *
     * @throws UsageException 実装が見つからない場合
     */
    private function createClient(Credentials $credentials): DmmApiClient
    {
        try {
            return new DmmApiClient($credentials, httpClient: $this->httpClient);
        } catch (NotFoundException $exception) {
            throw new UsageException(sprintf(
                '%s Example: composer require guzzlehttp/guzzle.',
                $exception->getMessage(),
            ));
        }
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

            return json_encode(
                $decoded,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . PHP_EOL;
        } catch (JsonException $exception) {
            $output->error('Could not pretty-print the response: ' . $exception->getMessage());

            return $body;
        }
    }

    /**
     * レスポンスが DTO と食い違った内容を標準エラー出力へ書き出す。
     *
     * 検証そのものは {@see DmmApiClient} が行う。ここが受け持つのは、その結果を
     * コンソールの形に整えることだけ。
     *
     * 検証エラーには、型が合わなかった値そのものが含まれる。認証情報を含む値であっても
     * 伏せ字は {@see Output} 側で適用されるため、ここでは何もしない。
     */
    private function reportValidationErrors(ResponseValidationException $exception, Output $output): void
    {
        $output->error(sprintf('Response did not match %s:', $exception->targetClass));

        foreach ($exception->errors as $error) {
            $output->error(sprintf('  %s: %s', $error['path'], $error['message']));
        }
    }
}
