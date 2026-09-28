<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // composer.json の autoload にないファイルは、パスに直接書けば対象になる。
    // PHPStan と Rector が見ている範囲に揃える。
    ->addPathToScan(__DIR__ . '/bin/dmm-api-client', isDev: false)
    ->addPathToScan(__DIR__ . '/tools/live-probe/probe.php', isDev: true)

    // Pest が対応する版を固定して入れるので、直接は require しない。
    ->ignoreErrorsOnPackage('phpunit/phpunit', [ErrorType::SHADOW_DEPENDENCY]);
