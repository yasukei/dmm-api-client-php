<?php

declare(strict_types=1);

namespace DmmApiClient\Response;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use DmmApiClient\Exception\ResponseValidationException;
use DmmApiClient\Response\ActressSearch\ActressSearchResponse;
use DmmApiClient\Response\AuthorSearch\AuthorSearchResponse;
use DmmApiClient\Response\Error\ErrorResponse;
use DmmApiClient\Response\FloorList\FloorListResponse;
use DmmApiClient\Response\GenreSearch\GenreSearchResponse;
use DmmApiClient\Response\ItemList\ItemListResponse;
use DmmApiClient\Response\MakerSearch\MakerSearchResponse;
use DmmApiClient\Response\SeriesSearch\SeriesSearchResponse;

/**
 * DMM API のレスポンス（デコード済みの JSON）を検証し、DTO へマッピングする。
 *
 * 入力にはデコード済みの配列（`json_decode($json, true)` の結果）を渡す。
 */
final readonly class ResponseMapper
{
    private TreeMapper $mapper;

    public function __construct(?TreeMapper $mapper = null)
    {
        $this->mapper = $mapper ?? self::defaultMapperBuilder()->mapper();
    }

    /**
     * 既定のマッパー設定。
     *
     * `allowSuperfluousKeys()` を加えて、DTO が知らないキーを無視する。DMM 側の項目追加で
     * マッピングが壊れないようにするためで、通常はこちらを使う。
     *
     * 代償として、項目が増えても気づけない。気づきたい場合は
     * {@see self::strictMapperBuilder()} を参照。
     */
    public static function defaultMapperBuilder(): MapperBuilder
    {
        return self::strictMapperBuilder()->allowSuperfluousKeys();
    }

    /**
     * DTO が知らないキーを検証エラーにするマッパー設定。
     *
     * DMM が新しい項目を返し始めたことに気づくための設定。既定の設定では知らないキーは
     * 黙って捨てられるため、増えても分からない。
     *
     * 検知はマッピングの失敗としてしか得られない（Valinor に警告という段階は無い）。
     * 値も使いたい場合は、既定のマッパーで読み取ったうえで、この設定で別に検証する。
     *
     * 共通の設定は次のとおり。
     * - `supportDateFormats()`: DMM が返す日付・日時の書式を `DateTimeImmutable` に変換する。
     *   先頭の `!` は、書式に含まれない要素（`Y-m-d` における時刻など）を
     *   現在時刻ではなくゼロで埋めるための指定。
     * - 型の暗黙変換（"1" -> 1 など）は許可しない（仕様との差異を検出するため）。
     */
    public static function strictMapperBuilder(): MapperBuilder
    {
        return (new MapperBuilder())
            ->supportDateFormats('!Y-m-d H:i:s', '!Y-m-d', '!Y-m-d\\TH:i:s');
    }

    /**
     * DTO が知らないキーを検証エラーにするマッパー。
     *
     * 知らないキーがあると {@see ResponseValidationException} になり、その `errors` には
     * {@see ResponseValidationException::CODE_UNEXPECTED_KEY} のコードが入る。
     */
    public static function strict(): self
    {
        return new self(self::strictMapperBuilder()->mapper());
    }

    public function itemList(mixed $payload): ItemListResponse
    {
        return $this->map(ItemListResponse::class, $payload);
    }

    public function floorList(mixed $payload): FloorListResponse
    {
        return $this->map(FloorListResponse::class, $payload);
    }

    public function actressSearch(mixed $payload): ActressSearchResponse
    {
        return $this->map(ActressSearchResponse::class, $payload);
    }

    public function genreSearch(mixed $payload): GenreSearchResponse
    {
        return $this->map(GenreSearchResponse::class, $payload);
    }

    public function makerSearch(mixed $payload): MakerSearchResponse
    {
        return $this->map(MakerSearchResponse::class, $payload);
    }

    public function seriesSearch(mixed $payload): SeriesSearchResponse
    {
        return $this->map(SeriesSearchResponse::class, $payload);
    }

    public function authorSearch(mixed $payload): AuthorSearchResponse
    {
        return $this->map(AuthorSearchResponse::class, $payload);
    }

    public function error(mixed $payload): ErrorResponse
    {
        return $this->map(ErrorResponse::class, $payload);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     *
     * @throws ResponseValidationException
     */
    public function map(string $class, mixed $payload): object
    {
        try {
            return $this->mapper->map($class, $payload);
        } catch (MappingError $error) {
            throw ResponseValidationException::fromMappingError($class, $error);
        }
    }
}
