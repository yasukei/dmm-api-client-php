# dmm-api-client-php

[![CI](https://github.com/yasukei/dmm-api-client-php/actions/workflows/ci.yml/badge.svg)](https://github.com/yasukei/dmm-api-client-php/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/yasukei/dmm-api-client-php.svg)](https://packagist.org/packages/yasukei/dmm-api-client-php)
[![License](https://img.shields.io/github/license/yasukei/dmm-api-client-php.svg)](LICENSE)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/yasukei/dmm-api-client-php/php)](composer.json)

A PHP client library for the [DMM Affiliate API](https://affiliate.dmm.com/api/) (Web Service API v3).

Every response is validated against a typed DTO with [Valinor](https://valinor.cuyz.io/) before it
reaches your code, so a change in the API surfaces as an exception with the exact JSON path rather
than as a `null` three layers down.

> **Status:** This library is under active development. The API may change without notice until a stable `v1.0.0` release.

## Requirements

- PHP 8.3 or later
- A [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and a [PSR-17](https://www.php-fig.org/psr/psr-17/) factory

This library does not bundle an HTTP client. It depends only on the PSR interfaces and discovers an
installed implementation at runtime, so you can keep using whichever client your project already has.

## Installation

```bash
composer require yasukei/dmm-api-client-php
```

If your project does not already provide a PSR-18 client, install one:

```bash
composer require guzzlehttp/guzzle
# or
composer require symfony/http-client nyholm/psr7
```

## Getting started

You need an API ID and an affiliate ID from the
[DMM Affiliate console](https://affiliate.dmm.com/api/). The affiliate ID must end in `990` through
`999` — the API rejects any other suffix, and `Credentials` checks this up front.

```php
use DmmApiClient\DmmApiClient;
use DmmApiClient\Request\Credentials;
use DmmApiClient\Request\ItemListRequest;
use DmmApiClient\Request\ItemListSort;
use DmmApiClient\SiteCode;

$client = new DmmApiClient(new Credentials('YOUR_API_ID', 'youraffiliateid-999'));

$response = $client->itemList(new ItemListRequest(
    site: SiteCode::Fanza,
    service: 'digital',
    floor: 'videoa',
    keyword: 'アクション',
    sort: ItemListSort::Date,
    hits: 20,
));

echo $response->result->totalCount, " items found\n";

foreach ($response->result->items as $item) {
    echo $item->contentId, ' ', $item->title, ' ', $item->prices?->price, "\n";
}
```

DMM requires applications built on this API to display the appropriate credit, such as
"Web Service by DMM.com" or "Web Service by FANZA".

## Endpoints

| Method | Endpoint | Request |
| --- | --- | --- |
| `itemList()` | `/ItemList` | `ItemListRequest` |
| `floorList()` | `/FloorList` | `FloorListRequest` (optional) |
| `actressSearch()` | `/ActressSearch` | `ActressSearchRequest` |
| `genreSearch()` | `/GenreSearch` | `GenreSearchRequest` |
| `makerSearch()` | `/MakerSearch` | `MakerSearchRequest` |
| `seriesSearch()` | `/SeriesSearch` | `SeriesSearchRequest` |
| `authorSearch()` | `/AuthorSearch` | `AuthorSearchRequest` |

Request objects take named arguments, and every optional parameter defaults to "not sent". Values
the API documents as bounded — `hits`, `offset`, `floor_id` — are checked in the constructor, so a
bad value fails locally instead of costing a round trip.

```php
use DmmApiClient\Request\ActressSearchRequest;
use DmmApiClient\Request\ActressSearchSort;
use DmmApiClient\Request\GenreSearchRequest;

$client->actressSearch(new ActressSearchRequest(
    keyword: 'あさみ',
    gteBust: 85,
    gteBirthday: new DateTimeImmutable('1990-01-01'),
    sort: ActressSearchSort::BustDesc,
    hits: 20,
));

$client->genreSearch(new GenreSearchRequest(floorId: '43', initial: 'あ'));

// Use /FloorList to discover the service codes, floor codes, and floor IDs
// the other endpoints expect.
foreach ($client->floorList()->result->site as $site) {
    foreach ($site->service as $service) {
        foreach ($service->floor as $floor) {
            echo $site->code->value, ' / ', $service->code, ' / ', $floor->id, ' ', $floor->name, "\n";
        }
    }
}
```

### Filtering by category

`/ItemList` can be narrowed by actress, author, genre, series, or maker. Pass one or more
`ArticleFilter` objects:

```php
use DmmApiClient\Request\ArticleFilter;
use DmmApiClient\Request\ArticleType;

$client->itemList(new ItemListRequest(
    site: SiteCode::Fanza,
    articles: [
        new ArticleFilter(ArticleType::Genre, '6533'),
        new ArticleFilter(ArticleType::Actress, '1078970'),
    ],
));
```

## Error handling

Every exception implements `DmmApiClient\Exception\DmmApiClientException`, so you can catch them
together or handle each cause separately.

| Exception | Raised when | Carries |
| --- | --- | --- |
| `InvalidArgumentException` | A request was built with an invalid value | — |
| `TransportException` | No response was received (DNS failure, timeout, …) | `endpoint` |
| `ApiErrorException` | The API answered with a non-2xx status | `httpStatusCode`, `error`, `responseBody` |
| `MalformedResponseException` | The body was not valid JSON | `endpoint`, `responseBody` |
| `ResponseValidationException` | The body did not match the expected structure | `targetClass`, `errors` |

```php
use DmmApiClient\Exception\ApiErrorException;
use DmmApiClient\Exception\ResponseValidationException;
use DmmApiClient\Exception\TransportException;

try {
    $response = $client->itemList($request);
} catch (ApiErrorException $e) {
    // DMM API returned 400 BAD REQUEST (affiliate_id: Invalid Request Error)
    $e->error?->message;
} catch (ResponseValidationException $e) {
    // [['path' => 'result.total_count', 'message' => "Value 'many' is not a valid integer."]]
    $e->errors;
} catch (TransportException $e) {
    // Retry, or fall back to a cached result.
}
```

`ApiErrorException::$error` is an `ErrorResult` when the error body could be parsed, and `null`
otherwise — for example when a proxy returns an HTML error page. The raw body is always available in
`$responseBody`.

## How responses are typed

The DTOs mirror what the API actually returns rather than what the values mean:

- **Numbers the API sends as strings stay strings.** `price` can be `"300~"` on some floors, and
  `bust` comes back as `"90"`. Converting eagerly would either lose data or throw on values the API
  is entitled to send.
- **Dates become `DateTimeImmutable`.** `Item::$date` and `Actress::$birthday` are parsed from the
  formats DMM uses.
- **Unknown keys are ignored.** New fields added by DMM will not break mapping.
- **Nothing is cast implicitly.** If a field the spec says is an `int` arrives as `"10"`, mapping
  fails rather than silently coercing, so the change surfaces as a bug report instead of bad data.

Site codes are mapped to a `SiteCode` enum. An unknown site code is a validation failure — that is
deliberate, so a new DMM site is noticed rather than silently ignored.

## Advanced usage

### Supplying your own HTTP client

Pass a PSR-18 client to control timeouts, retries, proxies, or logging. Anything transport-related
belongs to the client, not to this library.

```php
$client = new DmmApiClient(
    new Credentials('YOUR_API_ID', 'youraffiliateid-999'),
    new GuzzleHttp\Client(['timeout' => 10]),
);
```

### Inspecting the request URI

`buildUri()` returns the URI that would be sent, without sending it.

```php
echo $client->buildUri(new ItemListRequest(site: SiteCode::Fanza, hits: 1));
// https://api.dmm.com/affiliate/v3/ItemList?api_id=...&affiliate_id=...&site=FANZA&hits=1&output=json
```

### Validating a payload you fetched yourself

If you already have the JSON — from a cache, a fixture, or your own HTTP layer — `ResponseMapper`
performs the same validation on its own.

```php
use DmmApiClient\Response\ResponseMapper;

$response = (new ResponseMapper())->itemList(json_decode($json, true));
```

`ResponseMapper::defaultMapperBuilder()` exposes the underlying Valinor configuration if you need to
adjust it, and the constructor accepts any `TreeMapper`.

## Development

```bash
composer install
```

| Command | Purpose |
| --- | --- |
| `composer test` | Run the test suite ([Pest](https://pestphp.com/)) |
| `composer stan` | Static analysis ([PHPStan](https://phpstan.org/), level max) |
| `composer cs-check` / `composer cs-fix` | Code style ([PHP-CS-Fixer](https://cs.symfony.com/)) |
| `composer rector-dry` / `composer rector` | Automated refactoring ([Rector](https://getrector.com/)) |

The test suite never touches the network. HTTP is stubbed through a PSR-18 implementation in
`tests/Support/`, and the response shapes live as JSON in `tests/Fixtures/`.

## Contributing

Bug reports and pull requests are welcome. Please open an issue to discuss significant changes before submitting a pull request.

## License

This library is licensed under the [MIT License](LICENSE).

This project is not affiliated with, endorsed by, or sponsored by DMM.com LLC.
