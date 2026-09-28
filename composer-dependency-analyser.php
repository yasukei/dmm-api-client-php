<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // composer.json の autoload にない実行ファイルは、パスに直接書けば対象になる。
    ->addPathToScan(__DIR__ . '/bin/dmm-api-client', isDev: false)

    // Pest が対応する版を固定して入れるので、直接は require しない。
    ->ignoreErrorsOnPackage('phpunit/phpunit', [ErrorType::SHADOW_DEPENDENCY]);
