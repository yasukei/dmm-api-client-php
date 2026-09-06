<?php

declare(strict_types=1);

namespace DmmApiClient\Api\Response\ItemList;

/**
 * CD 商品に固有の情報。
 *
 * 返すのは DMM.com の CD フロアだけで、そのフロアの商品はすべて持つ。
 */
final readonly class CdInfo
{
    /**
     * @param string $kind 種別。実データで確認できたのは「アルバム」「シングル」の 2 種
     */
    public function __construct(
        public string $kind,
    ) {
    }
}
