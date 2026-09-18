<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

/**
 * 保存済みの `ItemList` レスポンスから、商品が持つ分類（`iteminfo`）の ID を数える。
 *
 * `article` / `article_id` に何を指定して試すかを、そのフロアの実データから決めるために使う。
 * フロアに 1 件も出ない分類を叩いても分かることは無いので、出た分類だけを対象にする。
 *
 * 公式ドキュメントが `article` に挙げているのは actress・author・genre・series・maker の 5 種だが、
 * `iteminfo` はそれ以外の分類（label、director、manufacture など）も返す。それらが `article` に
 * 使えるかはドキュメントからは分からない。使えるなら利用者にとっては素直に便利なので、実際に試す。
 *
 * ID は出現数の多い順に並べて持つ。最も多いものが「該当なし」を表す枠のことがあり、それで
 * 引くと 0 件になって、その分類が使えるのかどうかが分からないまま終わる。そうした値を
 * 名指しで避けるのではなく、空振りしたら次点で引き直せるようにする（{@see self::next()}）。
 *
 * 判定に使う「その商品がこの ID を持つか」も、同じ読み取りで済むのでここに置く。
 */
final readonly class ArticleTally
{
    /**
     * @param array<string, list<string>> $candidates 分類名 => 出現数の多い順に並べた ID
     */
    private function __construct(
        public array $candidates,
    ) {}

    /**
     * 分類ごとに、まず試す ID。
     *
     * @return array<string, string>
     */
    public function articles(): array
    {
        $articles = [];

        foreach ($this->candidates as $article => $ids) {
            if ($ids !== []) {
                $articles[$article] = $ids[0];
            }
        }

        return $articles;
    }

    /**
     * その ID の次に多かった ID。無ければ null。
     *
     * 空振りしたときに引き直すためのもの。
     */
    public function next(string $article, string $afterId): ?string
    {
        $ids = $this->candidates[$article] ?? [];
        $at = array_search($afterId, $ids, true);

        return $at === false ? null : ($ids[$at + 1] ?? null);
    }

    /**
     * @param iterable<string> $bodies 同じフロアの `ItemList` レスポンス
     */
    public static function fromBodies(iterable $bodies): self
    {
        /** @var array<string, array<string, int>> $counted */
        $counted = [];

        foreach ($bodies as $body) {
            foreach (self::itemsOf(Json::decode($body)) as $item) {
                foreach (self::articlesOf($item) as $article => $ids) {
                    foreach (array_unique($ids) as $id) {
                        $counted[$article][$id] = ($counted[$article][$id] ?? 0) + 1;
                    }
                }
            }
        }

        $candidates = [];

        foreach ($counted as $article => $ids) {
            $candidates[$article] = self::ranked($ids);
        }

        ksort($candidates);

        return new self($candidates);
    }

    /**
     * レスポンスに入っている商品。
     *
     * DTO を通さずに読むのは、検証に失敗するレスポンスからも分類を拾いたいため。
     *
     * @param array<mixed>|null $decoded
     *
     * @return list<array<mixed>>
     */
    public static function itemsOf(?array $decoded): array
    {
        $result = $decoded === null ? null : ($decoded['result'] ?? null);
        $items = is_array($result) ? ($result['items'] ?? null) : null;

        if (! is_array($items)) {
            return [];
        }

        $list = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * 商品 1 件が持つ、分類ごとの ID。
     *
     * @param array<mixed> $item
     *
     * @return array<string, list<string>>
     */
    public static function articlesOf(array $item): array
    {
        $iteminfo = $item['iteminfo'] ?? null;

        if (! is_array($iteminfo)) {
            return [];
        }

        $articles = [];

        foreach ($iteminfo as $article => $elements) {
            if (! is_string($article) || ! is_array($elements)) {
                continue;
            }

            foreach ($elements as $element) {
                $id = is_array($element) ? ($element['id'] ?? null) : null;

                if (is_int($id) || is_string($id)) {
                    $articles[$article][] = (string) $id;
                }
            }
        }

        return $articles;
    }

    /**
     * 商品がその分類の ID を持っているか。`article` が効いたかの判定に使う。
     *
     * @param array<mixed> $item
     */
    public static function carries(array $item, string $article, string $id): bool
    {
        return in_array($id, self::articlesOf($item)[$article] ?? [], true);
    }

    /**
     * 出現数の多い順に並べた ID。
     *
     * 同数で並んだ場合は ID の小さい方を先にする。実行のたびに試す ID が変わると、
     * 前回の結果と突き合わせられなくなるため。
     *
     * @param array<string, int> $ids
     *
     * @return list<string>
     */
    private static function ranked(array $ids): array
    {
        $pairs = [];

        foreach ($ids as $id => $count) {
            $pairs[] = [(string) $id, $count];
        }

        usort(
            $pairs,
            static fn(array $a, array $b): int => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]),
        );

        return array_map(static fn(array $pair): string => $pair[0], $pairs);
    }
}
