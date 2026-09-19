# Changelog

このプロジェクトの主な変更を記録する。形式は [Keep a Changelog](https://keepachangelog.com/ja/1.1.0/) に倣い、バージョンは [Semantic Versioning](https://semver.org/lang/ja/) に従う。`v1.0.0` までは、マイナーバージョンでも破壊的変更が入ることがある。

## [Unreleased]

### Added

- `DmmApiClientInterface` を追加し、`DmmApiClient` が実装するようにした (#40)
- 型付きメソッドが返す `*Response` に、生ボディを返す `body()` と、デコードした配列を返す `json()` を追加した (#35)
- `ResponseValidationException` に `$responseBody` を追加した (#35)

### Changed

- **破壊的変更:** 検索系 API の `*SearchResult::$totalCount` と `ActressSearchResult::$firstPosition` を `int` に揃えた (#34)
- **破壊的変更:** `ItemInfoElement::$id` を `string` に揃えた (#41)
- **破壊的変更:** `ItemPrices::$price` / `$listPrice` と `Delivery::$price` / `$listPrice` を `string` に揃えた (#42)
- **破壊的変更:** `ItemListRequest::$site` と `Credentials::$affiliateId` の空文字を `InvalidArgumentException` で拒否するようにした (#37)

### Removed

- **破壊的変更:** `DmmApiClient::lastResponseBody()` を削除した。代わりに `*Response` の `body()` / `json()` を使う (#35)
- **破壊的変更:** `DmmApiClient` のコンストラクタから `$streamFactory` 引数を削除した。後ろの `$responseMapper` の位置が 1 つ前にずれる (#35)

## [0.1.0] - 2026-09-19

初回リリース。

[Unreleased]: https://github.com/yasukei/dmm-api-client-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/yasukei/dmm-api-client-php/releases/tag/v0.1.0
