<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\MakerSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * メーカー情報 1 件。
 *
 * `list_url` はキーごと返らないことがある。一覧ページを持たないフロアがあり、
 * 同じ ID でもフロアによって返る・返らないが変わる。
 */
final readonly class Maker
{
    /**
     * @param string      $makerId     メーカー ID（例: "306073"）
     * @param string      $name        メーカー名
     * @param string      $ruby        メーカー名かな
     * @param string|null $listUrl     このメーカーの作品一覧へのアフィリエイトリンク
     * @param string|null $anotherName 別名
     */
    public function __construct(
        #[MapFromKey('maker_id')]
        public string $makerId,
        public string $name,
        public string $ruby,
        #[MapFromKey('list_url')]
        public ?string $listUrl = null,
        #[MapFromKey('another_name')]
        public ?string $anotherName = null,
    ) {}
}
