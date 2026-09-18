<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\Exception\InvalidArgumentException;
use DmmApiClient\Api\Request\Request;

/**
 * フロア ID を必須とする検索 API（ジャンル・メーカー・シリーズ・作者）の共通部分。
 *
 * 4 つとも受け付けるパラメータが同じなので、対象の呼び名だけを差し替える。
 *
 * @template TRequest of Request リクエストの具体型。各コマンドが対応する型を束縛する
 */
abstract class FloorScopedSearchCommand extends ApiCommand
{
    /**
     * 検索対象の呼び名（例: ジャンル）。ヘルプの文言に使う。
     */
    abstract protected function subject(): string;

    final protected function requestOptions(): array
    {
        return [
            new OptionDefinition('floor-id', sprintf('%sが属するフロアの ID（必須。例: 43）', $this->subject()), 'ID'),
            new OptionDefinition('initial', sprintf('%s名かなの前方一致（2 文字以上も指定できる。例: あ、あさ）', $this->subject()), 'KANA'),
            new OptionDefinition('hits', '取得件数（1〜500）', 'N'),
            new OptionDefinition('offset', '検索開始位置（1 以上）', 'N'),
        ];
    }

    /**
     * オプションからリクエストを組み立てる。
     *
     * {@see self::invoke()} の実装が `$client->genreSearch(...)` のような型付きメソッドへ
     * そのまま渡せるよう、戻り値を具体型のまま返す。
     *
     * @return TRequest
     *
     * @throws UsageException           オプションの値が不正な場合
     * @throws InvalidArgumentException リクエストが受け付けない値だった場合
     */
    final protected function createRequest(Input $input): Request
    {
        return $this->createFloorScopedRequest(
            $this->requiredOption($input, 'floor-id'),
            $input->option('initial'),
            $this->intOption($input, 'hits'),
            $this->intOption($input, 'offset'),
        );
    }

    /**
     * @return TRequest
     *
     * @throws InvalidArgumentException リクエストが受け付けない値だった場合
     */
    abstract protected function createFloorScopedRequest(
        string $floorId,
        ?string $initial,
        ?int $hits,
        ?int $offset,
    ): Request;
}
