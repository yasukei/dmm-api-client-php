<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
        // 拡張子のない実行ファイルは、パスに直接書けば対象になる。
        __DIR__ . '/bin/dmm-api-client',
    ])
    // ルート直下の rector.php と .php-cs-fixer.dist.php 自身も対象にする。
    // PHPStan と PHP-CS-Fixer が見ている範囲に揃える。
    ->withRootFiles()

    // 対象の PHP は composer.json の require から取る。版を直接書くと、
    // 最低要求を上げたときにここだけ古いまま残る。
    ->withPhpSets()

    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    // PHPUnit 向けのルールは、インストール済みの版に対応するものだけを使う。
    ->withComposerBased(phpunit: true)

    // 書き換えで挿入される型名が完全修飾のまま残らないようにする。
    // PHP-CS-Fixer の @PER-CS3x0 はこれを import に直さない。
    ->withImportNames()

    ->withCache(__DIR__ . '/.rector-cache')

    ->withSkip([
        // 変数名のない @var を「使われていない注釈」とみなして消すが、
        // tests/Support/Fixture.php では PHPStan が戻り値の型として読んでいる。
        RemoveNonExistingVarAnnotationRector::class,

        // `=== null` を `! instanceof` に置き換える。null かどうかを見ている箇所で、
        // 型の判定に読み替えさせる理由がない。
        FlipTypeControlToUseExclusiveTypeRector::class,

        // 意図して static にしてある private ヘルパを書き換える。
        // 加えて 1 度の実行で収束せず、rector を 2 回通さないと rector-dry が落ちる。
        LocallyCalledStaticMethodToNonStaticRector::class,
    ]);
