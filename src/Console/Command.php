<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\Exception\DmmApiClientException;

/**
 * `bin/dmm-api-client` のサブコマンド。
 */
interface Command
{
    /**
     * サブコマンド名（例: `floor-list`）。
     */
    public function name(): string;

    /**
     * 一覧に表示する 1 行の説明。
     */
    public function description(): string;

    /**
     * 受け付けるオプション。共通オプションを含む。
     *
     * @return list<OptionDefinition>
     */
    public function options(): array;

    /**
     * @return int 終了コード
     *
     * @throws DmmApiClientException 実行を続けられない場合。呼び出し側が文言を出して終了コードに変える
     */
    public function execute(Input $input, Environment $environment, Output $output): int;
}
