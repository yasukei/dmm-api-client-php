<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/tools'])
    // 拡張子のない実行ファイルと、ルート直下の設定ファイルは in() では拾えない。
    ->append([
        __DIR__ . '/bin/dmm-api-client',
        __FILE__,
        __DIR__ . '/rector.php',
    ]);

return (new Config())
    ->setRules([
        // 版を固定する。@PER-CS は Fixer の更新で指す版が変わり、CI の結果が勝手に動く。
        '@PER-CS3x0' => true,
        '@PER-CS3x0:risky' => true,
        '@PHP8x3Migration' => true,
        '@PHP8x3Migration:risky' => true,

        // 既存コードの書き方を、書き忘れや崩れが起きないようルールとして固定する。
        'declare_strict_types' => true,
        'not_operator_with_successor_space' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'exit', 'return', 'throw', 'try'],
        ],
        'phpdoc_align' => true,

        // 型の取り違えを比較と関数呼び出しの段階で防ぐ。PHPStan の strict-rules を補う。
        'strict_comparison' => true,
        'strict_param' => true,

        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_superfluous_elseif' => true,

        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'single_quote' => true,
    ])
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setFinder($finder);
