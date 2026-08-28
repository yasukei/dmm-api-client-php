<?php

declare(strict_types=1);

namespace DmmApiClient\Response\AuthorSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * 作者情報 1 件。
 */
final readonly class Author
{
    /**
     * @param string      $authorId    作者 ID（例: "21414"）
     * @param string      $name        作者名
     * @param string      $ruby        作者名かな
     * @param string|null $anotherName 別名義（スラッシュ区切り、例: 別名義/アナザーネーム）
     * @param string|null $listUrl     この作者の作品一覧へのアフィリエイトリンク
     */
    public function __construct(
        #[MapFromKey('author_id')]
        public string $authorId,
        public string $name,
        public string $ruby,
        #[MapFromKey('another_name')]
        public ?string $anotherName = null,
        #[MapFromKey('list_url')]
        public ?string $listUrl = null,
    ) {
    }
}
