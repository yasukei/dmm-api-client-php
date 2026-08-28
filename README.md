# dmm-api-client-php

[![Latest Version](https://img.shields.io/packagist/v/yasukei/dmm-api-client-php.svg)](https://packagist.org/packages/yasukei/dmm-api-client-php)
[![License](https://img.shields.io/github/license/yasukei/dmm-api-client-php.svg)](LICENSE)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/yasukei/dmm-api-client-php/php)](composer.json)

A PHP client library for the [DMM Affiliate API](https://affiliate.dmm.com/api/).

> **Status:** This library is under active development. The API may change without notice until a stable `v1.0.0` release.

## Requirements

- PHP 8.3 or later

## Installation

Install via [Composer](https://getcomposer.org/):

```bash
composer require yasukei/dmm-api-client-php
```

## Usage

```php
<?php

require __DIR__ . '/vendor/autoload.php';

// Usage examples will be added here as the library develops.
```

## Development

Clone the repository and install the dependencies:

```bash
composer install
```

### Testing

This project uses [Pest](https://pestphp.com/) for testing.

```bash
composer test
```

### Static Analysis

This project uses [PHPStan](https://phpstan.org/) for static analysis.

```bash
composer stan
```

### Code Style

This project uses [PHP-CS-Fixer](https://cs.symfony.com/) to enforce a consistent code style.

```bash
# Check for style violations
composer cs-check

# Automatically fix style violations
composer cs-fix
```

### Refactoring

This project uses [Rector](https://getrector.com/) to automate refactoring.

```bash
# Preview proposed changes
composer rector-dry

# Apply proposed changes
composer rector
```

## Contributing

Bug reports and pull requests are welcome. Please open an issue to discuss significant changes before submitting a pull request.

## License

This library is licensed under the [MIT License](LICENSE).

This project is not affiliated with, endorsed by, or sponsored by DMM.com LLC.
