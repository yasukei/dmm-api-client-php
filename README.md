# dmm-api-client-php

[![CI](https://github.com/yasukei/dmm-api-client-php/actions/workflows/ci.yml/badge.svg)](https://github.com/yasukei/dmm-api-client-php/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/yasukei/dmm-api-client-php.svg)](https://packagist.org/packages/yasukei/dmm-api-client-php)
[![License](https://img.shields.io/github/license/yasukei/dmm-api-client-php.svg)](LICENSE)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/yasukei/dmm-api-client-php/php)](composer.json)

[DMM Web サービス API](https://affiliate.dmm.com/api/)（v3）の PHP クライアントライブラリ。

レスポンスは [Valinor](https://valinor.cuyz.io/) で検証してから型付きの DTO として返す。
API の仕様と食い違うレスポンスは、`null` になって埋もれるのではなく、JSON パス付きの例外になる。

> **状態:** 開発中。`v1.0.0` までは予告なく API が変わる可能性がある。

## 動作要件

- PHP 8.3 以降
- [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP クライアントと [PSR-17](https://www.php-fig.org/psr/psr-17/) ファクトリの実装（インストール済みのものを自動検出する）

## インストール

```bash
composer require yasukei/dmm-api-client-php
```

PSR-18 クライアントをまだ入れていない場合は、あわせてインストールする。

```bash
composer require guzzlehttp/guzzle
# または
composer require symfony/http-client nyholm/psr7
```

## 使い方

```php
use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\Request\Credentials;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\ItemListSort;
use DmmApiClient\Api\SiteCode;

$client = new DmmApiClient(new Credentials('your_api_id', 'your_affiliate_id'));

$response = $client->itemList(new ItemListRequest(
    site: SiteCode::DmmCom,
    service: 'mono',
    floor: 'book',
    keyword: 'PHP',
    sort: ItemListSort::Date,
    hits: 20,
));

echo $response->result->totalCount, " 件\n";

foreach ($response->result->items as $item) {
    echo $item->contentId, ' ', $item->title, ' ', $item->prices?->price, "\n";
}
```

リクエストの型は `src/Api/Request`、レスポンスの型は `src/Api/Response` 配下にある。
各パラメータ・項目の意味は、それぞれのクラスの docblock を参照。

DMM API を使ったサイトやアプリケーションには [クレジット表示](https://affiliate.dmm.com/api/credit.html) が必要。

## 対応 API

| API | メソッド | リクエスト |
| --- | --- | --- |
| `/ItemList` | `itemList()` | `ItemListRequest` |
| `/FloorList` | `floorList()` | `FloorListRequest`（省略可） |
| `/ActressSearch` | `actressSearch()` | `ActressSearchRequest` |
| `/GenreSearch` | `genreSearch()` | `GenreSearchRequest` |
| `/MakerSearch` | `makerSearch()` | `MakerSearchRequest` |
| `/SeriesSearch` | `seriesSearch()` | `SeriesSearchRequest` |
| `/AuthorSearch` | `authorSearch()` | `AuthorSearchRequest` |

## エラー処理

ライブラリが投げる例外は、すべて `DmmApiClient\Api\Exception\DmmApiClientException` を実装している。

| 例外 | 発生する場面 |
| --- | --- |
| `InvalidArgumentException` | リクエストを不正な値で組み立てた |
| `TransportException` | HTTP 通信に失敗した（DNS 失敗、タイムアウトなど） |
| `ApiErrorException` | API がエラーを返した（`$error` にエラー内容が入る） |
| `MalformedResponseException` | レスポンスが JSON として読めなかった |
| `ResponseValidationException` | レスポンスが期待する構造と一致しなかった（`$errors` に JSON パスと理由が入る） |

## コマンド

レスポンスを確認するための `dmm-api-client` コマンドを同梱している。
認証情報は環境変数 `DMM_API_ID` / `DMM_AFFILIATE_ID`、またはカレントディレクトリの `.env` から読む。

```bash
vendor/bin/dmm-api-client --help
vendor/bin/dmm-api-client item-list --site=DMM.com --keyword=PHP | jq .

# このリポジトリを直接 clone して使う場合は ./bin/dmm-api-client
```

出力に含まれる認証情報は、既定で `***` に伏せ字にする。

## 開発

| コマンド | 用途 |
| --- | --- |
| `composer test` | 単体テスト（[Pest](https://pestphp.com/)） |
| `composer stan` | 静的解析（[PHPStan](https://phpstan.org/)） |
| `composer cs-check` / `composer cs-fix` | コードスタイル（[PHP-CS-Fixer](https://cs.symfony.com/)） |
| `composer rector-dry` / `composer rector` | 自動リファクタリング（[Rector](https://getrector.com/)） |
| `composer probe` | 実際の API から取得したデータで DTO を検証する。詳細は [tools/live-probe](tools/live-probe/README.md) |

## ライセンス

[MIT](LICENSE)
