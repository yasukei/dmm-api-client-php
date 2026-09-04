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

PSR-18 implementations tend to append the request URI to their exception messages, which would put
your credentials into any log that records a failed request. `TransportException::getMessage()` is
therefore masked before the exception is built.

The masking covers that message and nothing else. The original PSR-18 exception stays attached as
`getPrevious()` with its message untouched, because it belongs to the HTTP client and cannot be
rewritten. `Exception::__toString()` concatenates the whole chain, so anything that stringifies the
exception still prints the unmasked URI:

```php
$logger->error($e->getMessage());   // masked
$logger->error((string) $e);        // NOT masked — includes the previous exception
error_log($e);                      // NOT masked — same reason
throw $e;                           // NOT masked if it goes uncaught — PHP prints the chain
```

Log `getMessage()`, or run the string through `CredentialMasker` first. For the same reason,
`ApiErrorException::$responseBody` and `MalformedResponseException::$responseBody` are raw by
design — mask them before logging them.

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

## Command line

The package ships a `dmm` command for inspecting what the API actually returns. It prints the
response to stdout, so it composes with `jq`, `>`, and the rest of your shell.

```bash
vendor/bin/dmm                    # list the commands
vendor/bin/dmm floor-list         # call /FloorList and print the response
vendor/bin/dmm item-list --help   # options for one command
```

There is one subcommand per endpoint: `item-list`, `floor-list`, `actress-search`, `genre-search`,
`maker-search`, `series-search`, and `author-search`. Request parameters are options, named after
the query keys with `_` written as `-`.

```bash
dmm item-list --site=FANZA --floor=videoa --sort=date --hits=5
dmm item-list --site=FANZA --article=genre --article-id=6533 --article=actress --article-id=1078970
dmm actress-search --keyword=あさみ --gte-bust=85 --sort=-bust
dmm genre-search --floor-id=43 --initial=あ
```

`--article` and `--article-id` may be repeated, as long as both are given the same number of times.
Dates accept `2016-04-01`, `2016-04-01T12:34:56`, or `2016-04-01 12:34:56`. Options that map to an
enum list their accepted values in `--help`, and reject anything else before a request is sent.

Working inside this repository, the binary is at `./bin/dmm`.

### Credentials

Credentials are read from the `DMM_API_ID` and `DMM_AFFILIATE_ID` environment variables, or from a
`.env` file in the current directory. Copy `.env.example` to `.env` to get started — `.env` is
already in `.gitignore`.

```bash
DMM_API_ID=your_api_id
DMM_AFFILIATE_ID=youraffiliateid-999
```

An environment variable wins over the same key in `.env`. Point `--env-file` at another path to read
a different file.

Credentials cannot be passed as command-line options. Arguments are visible to other users through
`ps` and are recorded in shell history, which makes them a poor place for a secret.

The `.env` reader is deliberately minimal: `KEY=value`, `#` comments, an optional `export ` prefix,
and single or double quotes around a value. No variable interpolation, no multi-line values.

### Common options

| Option | Effect |
| --- | --- |
| `--dry-run` | Print the request URI and exit without sending it |
| `--raw` | Print the response body unmodified instead of pretty-printing it |
| `--no-validate-request` | Send the parameters as typed, skipping the client-side checks |
| `--no-validate-response` | Skip the DTO validation pass |
| `--no-mask` | Print the real credentials instead of masking them |
| `--env-file=PATH` | Read this file instead of `./.env` |

### Masked credentials

Your API ID and affiliate ID come back in the response — in the echoed request parameters, and
inside every `affiliateURL`, `list_url`, and `listURL` as `af_id=…`. Saving a response to a file
would otherwise put them in your repository, so the command replaces them with `***` by default.

```console
$ dmm item-list --site=FANZA | grep affiliate
    "affiliate_id": "***",
    "affiliateURL": "https://al.dmm.co.jp/?lurl=…&af_id=***",
```

`--no-mask` prints the real values, which you need when checking that an affiliate link was built
correctly or when copying the URI from `--dry-run` into curl. Masking only substitutes the
credential strings, so the JSON structure is untouched and a masked response still maps onto the
DTOs — which is what makes it usable as test data.

Masking applies to everything the command writes, stderr included. Validation errors quote the value
that did not match, and that value can be the echoed request parameters, so `dmm … 2> err.log` would
otherwise write your credentials into the log.

The same masking is available from PHP through `CredentialMasker`, for anyone logging responses:

```php
$safe = CredentialMasker::forCredentials($credentials)->mask($body);
```

Note that the substitution is a plain substring replacement, so a very short credential can match
unrelated text. Over-masking is the safe direction, so this is left as is.

By default the response is validated against the DTOs after it is printed. A mismatch is reported on
stderr with the offending JSON path and exits non-zero, while the body still goes to stdout — so a
change on DMM's side is visible rather than silent.

`--no-validate-request` bypasses the request objects entirely and forwards each option value
verbatim — including the affiliate ID suffix rule — which is how you find out what the API really
does with a value the library would have rejected. Required options stop being required, enums stop
being checked, and repeated options are sent as a list.

```console
$ dmm item-list --site=BOGUS --sort=nonexistent --hits=9999 --no-validate-request --dry-run
https://api.dmm.com/affiliate/v3/ItemList?api_id=…&site=BOGUS&sort=nonexistent&hits=9999&output=json
```

The same escape hatch is available from PHP through `RawRequest` and `Credentials::unchecked()`:

```php
$body = (new DmmApiClient(Credentials::unchecked('YOUR_API_ID', 'anything')))
    ->fetchRaw(new RawRequest(ItemListRequest::ENDPOINT, ['site' => 'FANZA', 'hits' => '9999']));
```

```console
$ dmm floor-list > floors.json
Response did not match DmmApiClient\Response\FloorList\FloorListResponse:
  result.site.0.code: Value 'NEWSITE' does not match any of 'DMM.com', 'FANZA'.
```

Exit codes are `0` on success, `1` when the call or the validation failed, and `2` when the command
line itself was wrong.

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

To check the DTOs against a wide slice of real data, `composer probe` walks every floor returned by
`FloorList` and calls the other endpoints with every sort order and the first/middle/last page of
results, then reports where the responses did not match the DTOs. It is a separate tool, not part of
`composer test`; see [`tools/live-probe/README.md`](tools/live-probe/README.md). The responses it
downloads are never committed.

## Contributing

Bug reports and pull requests are welcome. Please open an issue to discuss significant changes before submitting a pull request.

## License

This library is licensed under the [MIT License](LICENSE).

This project is not affiliated with, endorsed by, or sponsored by DMM.com LLC.
