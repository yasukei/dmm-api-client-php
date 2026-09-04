<?php

declare(strict_types=1);

namespace DmmApiClient\Response\GenreSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * ジャンル情報 1 件。
 *
 * `list_url` はキーごと返らないことがある。一覧ページを持たないフロアがあり、
 * 同じ ID でもフロアによって返る・返らないが変わる。
 */
final readonly class Genre
{
    /**
     * @param string      $genreId ジャンル ID（例: "43"）
     * @param string      $name    ジャンル名（例: アクション）
     * @param string      $ruby    ジャンル名かな（例: あくしょん）
     * @param string|null $listUrl このジャンルの作品一覧へのアフィリエイトリンク
     */
    public function __construct(
        #[MapFromKey('genre_id')]
        public string $genreId,
        public string $name,
        public string $ruby,
        #[MapFromKey('list_url')]
        public ?string $listUrl = null,
    ) {
    }
}
