#!/usr/bin/env php
<?php

declare(strict_types=1);

use DmmApiClient\LiveProbe\Probe;

$autoload = __DIR__ . '/../../vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, 'Could not locate the Composer autoloader. Run "composer install" first.' . PHP_EOL);

    exit(2);
}

require $autoload;

if (! class_exists(Probe::class)) {
    fwrite(STDERR, 'The live-probe classes are not autoloaded. Run "composer dump-autoload" first.' . PHP_EOL);

    exit(2);
}

/** @var list<string> $argv */
exit(Probe::main($argv, __DIR__ . '/runs'));
