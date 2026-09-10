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
 * 判定に使う「その商品がこの ID を持つか」も、同じ読み取りで済むのでここに置く。
 */
final readonly class ArticleTally
{
    /**
     * @param array<string, string> $articles 分類名 => そのフロアで最も多く出現した ID
     * @param array<string, int>    $counts   分類名 => その ID を持っていた商品の数
     */
    private function __construct(
        public array $articles,
        public array $counts,
    ) {
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

        $articles = [];
        $counts = [];

        foreach ($counted as $article => $ids) {
            [$id, $count] = self::mostFrequent($ids);
            $articles[$article] = $id;
            $counts[$article] = $count;
        }

        ksort($articles);
        ksort($counts);

        return new self($articles, $counts);
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
     * 最も多く出現した ID。
     *
     * 同数で並んだ場合は ID の小さい方を採る。実行のたびに試す ID が変わると、
     * 前回の結果と突き合わせられなくなるため。
     *
     * @param array<string, int> $ids
     *
     * @return array{string, int}
     */
    private static function mostFrequent(array $ids): array
    {
        $bestId = '';
        $bestCount = -1;

        foreach ($ids as $id => $count) {
            $id = (string) $id;

            if ($count > $bestCount || ($count === $bestCount && strcmp($id, $bestId) < 0)) {
                $bestId = $id;
                $bestCount = $count;
            }
        }

        return [$bestId, max($bestCount, 0)];
    }
}
