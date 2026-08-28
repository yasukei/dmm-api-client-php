<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * サンプル動画のプレイヤー URL とフラグ。
 */
final class SampleMovieUrl
{
    /**
     * @param int         $pcFlag      PC 向けサンプル動画の有無（0: なし、1: あり）
     * @param int         $spFlag      スマートフォン向けサンプル動画の有無（0: なし、1: あり）
     * @param string|null $size476x306 476x306 サイズのプレイヤー URL
     * @param string|null $size560x360 560x360 サイズのプレイヤー URL
     * @param string|null $size640x400 640x400 サイズのプレイヤー URL
     * @param string|null $size720x480 720x480 サイズのプレイヤー URL
     */
    public function __construct(
        #[MapFromKey('pc_flag')]
        public readonly int $pcFlag,
        #[MapFromKey('sp_flag')]
        public readonly int $spFlag,
        #[MapFromKey('size_476_306')]
        public readonly ?string $size476x306 = null,
        #[MapFromKey('size_560_360')]
        public readonly ?string $size560x360 = null,
        #[MapFromKey('size_640_400')]
        public readonly ?string $size640x400 = null,
        #[MapFromKey('size_720_480')]
        public readonly ?string $size720x480 = null,
    ) {
    }
}
