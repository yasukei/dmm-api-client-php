<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // composer.json の autoload にない実行ファイルは、パスに直接書けば対象になる。
    ->addPathToScan(__DIR__ . '/bin/dmm-api-client', isDev: false)

    // src/ は PSR-7 のメソッドを呼んでいるが、インタフェース名を書いていないので検出されない。
    ->ignoreErrorsOnPackage('psr/http-message', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])

    // Pest が対応する版を固定して入れるので、直接は require しない。
    ->ignoreErrorsOnPackage('phpunit/phpunit', [ErrorType::SHADOW_DEPENDENCY]);
