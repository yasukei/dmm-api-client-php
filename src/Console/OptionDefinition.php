<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

/**
 * コマンドが受け付けるオプション 1 つ分の定義。
 */
final readonly class OptionDefinition
{
    /**
     * @param string      $name        オプション名（`--` を除いた形。例: api-id）
     * @param string      $description ヘルプに表示する説明
     * @param string|null $placeholder 値を取るオプションの、ヘルプ上の値の表記（例: ID）。
     *                                 null の場合は値を取らないフラグとして扱う
     */
    public function __construct(
        public string $name,
        public string $description,
        public ?string $placeholder = null,
    ) {
    }

    public function takesValue(): bool
    {
        return $this->placeholder !== null;
    }

    /**
     * ヘルプの左側に表示する見出し（例: `--api-id=ID`）。
     */
    public function label(): string
    {
        return $this->takesValue() ? sprintf('--%s=%s', $this->name, $this->placeholder) : '--' . $this->name;
    }
}
