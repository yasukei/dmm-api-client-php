# live-probe

実データを広く集めて、このライブラリの DTO（レスポンスの型定義）が実際の API と合っているかを確かめるツール。

公式ドキュメントと少数の実データだけを頼りに定義した DTO は、フロアや並び順によって形が変わる項目を
取りこぼしやすい。そこで `FloorList` で全フロアを取り出し、フロアごとに残りの API を
**sort の全種別 × 先頭・中間・末尾のページ** で叩き、受け取ったレスポンスを 1 リクエスト 1 ファイルで
保存しながら DTO へのマッピングを試す。

見るのは 2 方向で、どちらも最後にまとめてレポートにする。**DTO が要求する形と実データが食い違う箇所**
（検証の失敗）と、**DTO が知らないまま捨てているフィールド**（未知のキー）。前者は本ライブラリの利用者に
そのまま例外として現れるので、実行を失敗させる。後者は黙って捨てられるだけで壊れはしないので、
報告だけして失敗にはしない。

`composer test` からは独立している。ネットワークと認証情報が要るため。

## 取得するもの

| API | 単位 | hits | ページ | 備考 |
| --- | --- | --- | --- | --- |
| `ItemList` | site + service + floor | 100 | 先頭・中間・末尾 | sort 6 種すべて（1 ページに収まるフロアは date だけ）。最後に article・複数 article・mono_stock で叩き直す |
| `GenreSearch` | floor_id | 500 | 先頭・中間・末尾 | |
| `MakerSearch` | floor_id | 500 | 先頭・中間・末尾 | |
| `SeriesSearch` | floor_id | 500 | 先頭・中間・末尾 | |
| `AuthorSearch` | floor_id | 500 | 先頭・中間・末尾 | |
| `ActressSearch` | フロアに依存しない | 100 | 先頭・中間・末尾 | sort 14 種すべて |
| `Errors` | フロアに依存しない | なし | なし | ケースごとに 1 件 |

中間と末尾のページ位置は、先頭ページの `total_count` から決める（末尾 = `total_count - hits + 1`、
中間 = `total_count / 2`。いずれも 1〜50000 に収める）。総件数が 1 ページに収まる場合は先頭だけを取る。

そうしたフロアでは sort も振らない。ページを振れないので、sort を変えても同じ商品が並び替わって返るだけで、
DTO に通す形は増えない。`sort=date` を 1 本叩いて次のフロアへ移る。sort ごとの違いは総件数の多いフロアで
見られている。

フロア × API の中には、そのフロアには存在しない組み合わせ（動画フロアの `AuthorSearch` など）も含まれる。
0 件で返るのが通常なので、これは失敗ではなく通常の成功として扱う。API がエラーを返した場合は
`api-error` として記録し、エラーボディも保存したうえで `ErrorResponse` として検証する。

`ItemList` は最後に、フロアごとの `iteminfo` に実際に出た分類を `article` / `article_id` に指定して
叩き直す。何が指定できるかはフロアの中身次第なので、対象は取得してみるまで決まらない。公式ドキュメントに
載っていない分類（`label` や `director` など）も使えるかどうかを確かめる。取るのは先頭ページだけで、
sort は指定しない。0 件で返った ID は、次に多い ID で 1 度だけ引き直す。詳細は
[`src/ArticleTally.php`](src/ArticleTally.php) と [`src/Planner.php`](src/Planner.php) の
`articleTarget()` にある。

続けて、サイトごとに 1 フロアだけ選び、分類を 2 つ・3 つまとめて指定して叩く（計 4 リクエスト）。
分類ごとの可否ではなく、複数指定そのものが成り立つか — エコーバックが指定どおり返るか、絞り込みが
重ねて効くか — を見るため、全フロアでやる必要は無い。選ぶのはそのサイトで分類がいちばん多いフロアで、
指定する値は、そのフロアの商品 1 件が実際に持っている組み合わせ。分類ごとに最頻出の ID を別々に
選ぶと、重ねたときに該当なしで返りかねないため。

続けて、通販（mono）のフロアを `mono_stock` の値ごとに叩き直す。`MonoStock` の全種を送る。
絞り込みには使えないと docblock が書いている値も含めるのは、そう書いてあるだけで全フロアで裏を
取ったわけではないため。詳細は [`src/Planner.php`](src/Planner.php) の `monoStockTargets()` にある。

`Errors` は、確実にエラーになるリクエストを送って `ErrorResponse` を検証するための対象。何をどう誤らせて
いるかは [`src/Planner.php`](src/Planner.php) の `errorCases()` にある。

フロアが 30 ほどあるとして、全体で 800〜900 リクエスト。既定の 1 req/秒で 15〜20 分、
出力は 100〜200MB 程度になる。

## 使い方

`.env`（またはプロセスの環境変数）に `DMM_API_ID` と `DMM_AFFILIATE_ID` が要る。

```bash
composer probe                              # 全フロア・全 API
composer probe -- --help                    # オプション一覧

# わざとエラーを引く分だけ（3 リクエスト）
composer probe -- --endpoint=Errors

# 取得済みの ItemList から、article の検証だけをやり直す
composer probe -- --endpoint=ItemList --resume

# 最初は対象を絞って様子を見る
composer probe -- --floor=videoa --endpoint=ItemList
composer probe -- --endpoint=ActressSearch --limit=5

# 途中で止めた／落ちたところから
composer probe -- --resume

# 取得済みのデータで検証だけやり直す（ネットワーク不要）
composer probe -- --revalidate
composer probe -- --revalidate --run=tools/live-probe/runs/20260904-120000
```

DTO を直したら `--revalidate` を回す、というのが基本のループになる。取得し直す必要はない。

## 出力

実行のたびに、日時を名前にしたディレクトリを新しく作る。過去の実行を上書きしないため。

```
tools/live-probe/runs/20260904-120000/
  FloorList/floor-list.json
  ItemList/FANZA__digital__videoa-43__sort-date__hits-100__offset-000001.json
  GenreSearch/FANZA__digital__videoa-43__hits-500__offset-000401.json
  ActressSearch/all__sort--birthday__hits-100__offset-000065.json
  Articles/FANZA__digital__videoa-43__article-actress-1234__hits-100__offset-000001.json
  MonoStock/FANZA__mono__dvd-74__mono_stock-stock__hits-100__offset-000001.json
  Errors/invalid-api-id.json
  manifest.jsonl   1 リクエスト 1 行。条件・URI・件数・検証結果・DTO が知らないキー
  run.json         実行条件と集計
  failures.json    検証に失敗した箇所と、DTO が知らないキーの一覧
  failures.md      同上（読む用）
```

ファイル名だけでサイト・サービス・フロア・sort・hits・offset が読み取れる。実際に送った URI は
`manifest.jsonl` にある。

`--revalidate` は同じディレクトリの `manifest.jsonl` / `failures.json` / `failures.md` を作り直す。

レポートが集計するのは、その回に処理した分だけ。run ディレクトリに溜まっている過去の分は含めない。
`manifest.jsonl` は run ディレクトリの記録なので過去の分も残るが、取得中は 1 件ずつ追記するため
（途中で止まっても結果が残るように）`--resume` では同じレスポンスの行が並ぶ。最後に 1 行へまとめ直す。

### レポート

`failures.md` は 6 節に分かれている。

| 節 | 内容 | 実行を失敗させるか |
| --- | --- | --- |
| Validation failures | DTO へマッピングできなかった箇所 | する |
| Keys the DTOs do not know | DMM が返しているが、DTO が知らないフィールド | しない |
| API errors | API がエラーを返したリクエスト | しない |
| Transport errors | レスポンスを受け取れなかったリクエスト | する |
| Article filters | `article` を指定した結果が、実際に絞り込めていたか | しない |
| Stock filters | `mono_stock` を指定した結果が、実際に絞り込めていたか | しない |

前の 2 節は、DTO のフィールド（配列の添字を `*` に均したパス）で束ねてある。同じ食い違いが数百件出ても
1 つの見出しにまとまり、直すべき箇所の数がそのまま見出しの数になる。見出しごとに、実際に来ていた値と、
再現できるファイル名を数件ぶら下げている。

```
### `result.items.*.review.count` (12 requests)

- 444× Value '12' is not a valid integer.

- `ItemList/DMM.com__mono__dvd-10__sort-rank__hits-100__offset-000125.json`
  - request: /ItemList DMM.com mono dvd sort=rank offset=125
  - path: `result.items.76.review.count`
  - value: `"12"`
```

API errors はエラーメッセージで束ねてある。存在しない組み合わせを叩けば、同じエラーが何十件も出るため。
わざとエラーを引いた分もここに並ぶ。Transport errors は束ねず、リクエストごとにそのまま並べる。

標準出力には、パスと件数だけに切り詰めたサマリを出す。実例まで見たいときに `failures.md` を開く。

### 終了コード

検証に失敗したリクエストか通信エラーがあれば 1、なければ 0。

API がエラーを返しただけ（`api-error`）では 1 にしない。存在しない組み合わせを叩けば必ず起きるため。
DTO が知らないキーがあっても 1 にしない。DTO がそれを無視しても、ライブラリの利用者には何も起きないため。

わざとエラーを引く対象が成功してしまった場合（`unexpected-ok`）は 1 にする。検証の失敗として、
`*outcome*` というパスで報告する。

`article` や `mono_stock` が効かなかった場合は 1 にしない。指定できると思っていた値が使えないのは
発見であって、ライブラリの不具合ではないため。

## 備考

### DTO が知らないキーの検出方法

本ライブラリの既定のマッパーは、DTO が宣言していないフィールドを黙って捨てる。API がフィールドを増やしても
利用者のコードが壊れないようにするためだが、代償として**増えたこと自体に気づけない**。
そこで probe は、各レスポンスをもう一度、未知のフィールドをエラーにするマッパーで読み直す。

2 つのマッパーはどちらも本体の `DmmApiClient\Api\Response\ResponseMapper` で、違いは
`allowSuperfluousKeys()` の有無だけ。probe はこれを両方持つ。

| | 使うマッパー | 報告先 |
| --- | --- | --- |
| 検証の失敗 | `new ResponseMapper()`（既定。未知のフィールドを無視する） | Validation failures |
| 未知のキー | `ResponseMapper::strict()`（未知のフィールドをエラーにする） | Keys the DTOs do not know |

厳密なマッパーは型の食い違いも同じ例外で返すので、`ResponseValidationException::CODE_UNEXPECTED_KEY`
のエラーだけを取り出して、既定のマッパーの報告と二重に数えないようにしている。
実際の突き合わせは [`src/Validator.php`](src/Validator.php) にある。

見つかったキーは、DTO に取り込むかどうかを判断するための材料になる。

```
keys the DTOs do not know (7 paths, 300 responses):
  result.items.*.stock            180
  result.items.*.directory        180
  result.items.*.jancode          141
```

### 保存するデータの扱い

- `runs/` は `.gitignore` 済み。**コミットしない。** API の規約上、取得したデータを誰でも見られる場所に
  置いてはならないため。
- 保存するレスポンスは、既定で API ID とアフィリエイト ID を伏せ字にする（`--no-mask` で無効化）。
  これらはリクエストのエコーバックにも `affiliateURL` にも埋め込まれて返ってくる。
  検証は伏せ字にする前のボディに対して行うので、伏せ字が検証結果を変えることはない。
