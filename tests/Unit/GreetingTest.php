<?php

declare(strict_types=1);

use DmmApiClient\Greeting;

test('hello returns the greeting string', function () {
    expect(Greeting::hello())->toBe('hello');
});
