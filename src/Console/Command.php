<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

/**
 * `bin/dmm` のサブコマンド。
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
     */
    public function execute(Input $input, Environment $environment, Output $output): int;
}
