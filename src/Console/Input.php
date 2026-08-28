<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Exception\UsageException;

/**
 * コマンド名より後ろの引数を、定義済みのオプションに従って解釈した結果。
 *
 * 値を取るオプションは `--name=value` と `--name value` の両方の書き方を受け付ける。
 * 同じオプションを繰り返し指定した場合は、指定された順にすべて保持する。
 * 定義にないオプションは、打ち間違いを見逃さないようエラーにする。
 */
final readonly class Input
{
    /**
     * @param array<string, non-empty-list<string>> $values 値を取るオプションの、指定された値
     * @param list<string>                          $flags  指定されたフラグの名前
     */
    private function __construct(
        private array $values,
        private array $flags,
    ) {
    }

    /**
     * @param list<string>             $tokens      コマンド名より後ろの引数
     * @param list<OptionDefinition>   $definitions 受け付けるオプション
     *
     * @throws UsageException 未定義のオプション、値の欠落、オプション以外の引数があった場合
     */
    public static function parse(array $tokens, array $definitions): self
    {
        $known = [];

        foreach ($definitions as $definition) {
            $known[$definition->name] = $definition;
        }

        $values = [];
        $flags = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if (! str_starts_with($token, '--')) {
                throw new UsageException(sprintf('Unexpected argument "%s".', $token));
            }

            $name = substr($token, 2);
            $inlineValue = null;

            if (str_contains($name, '=')) {
                [$name, $inlineValue] = explode('=', $name, 2);
            }

            $definition = $known[$name] ?? throw new UsageException(sprintf('Unknown option "--%s".', $name));

            if (! $definition->takesValue()) {
                if ($inlineValue !== null) {
                    throw new UsageException(sprintf('Option "--%s" does not take a value.', $name));
                }

                $flags[] = $name;

                continue;
            }

            if ($inlineValue !== null) {
                $values[$name][] = $inlineValue;

                continue;
            }

            $next = $tokens[$index + 1] ?? null;

            if ($next === null || str_starts_with($next, '--')) {
                throw new UsageException(sprintf('Option "--%s" requires a value.', $name));
            }

            $values[$name][] = $next;
            $index++;
        }

        return new self($values, $flags);
    }

    /**
     * 指定された値。繰り返し指定された場合は最後のものを返す。
     */
    public function option(string $name): ?string
    {
        $values = $this->values[$name] ?? null;

        return $values === null ? null : $values[count($values) - 1];
    }

    /**
     * 指定された値を、指定された順にすべて返す。
     *
     * @return list<string>
     */
    public function optionValues(string $name): array
    {
        return $this->values[$name] ?? [];
    }

    public function flag(string $name): bool
    {
        return in_array($name, $this->flags, true);
    }
}
