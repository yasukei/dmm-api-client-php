<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DateTimeImmutable;

/**
 * 女優情報 1 件。
 */
final class Actress
{
    /**
     * @param string                 $id          女優 ID（例: "12345"）
     * @param string                 $name        女優名
     * @param string                 $ruby        女優名かな
     * @param int|null               $bust        バスト（cm）
     * @param string|null            $cup         カップ数（例: G）
     * @param int|null               $waist       ウエスト（cm）
     * @param int|null               $hip         ヒップ（cm）
     * @param int|null               $height      身長（cm）
     * @param DateTimeImmutable|null $birthday    生年月日
     * @param string|null            $bloodType   血液型（例: A）
     * @param string|null            $hobby       趣味
     * @param string|null            $prefectures 出身地（例: 東京都）
     * @param ActressImageUrl|null   $imageUrl    プロフィール画像
     * @param ActressListUrl|null    $listUrl     出演作品一覧へのリンク
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $ruby,
        public readonly ?int $bust = null,
        public readonly ?string $cup = null,
        public readonly ?int $waist = null,
        public readonly ?int $hip = null,
        public readonly ?int $height = null,
        public readonly ?DateTimeImmutable $birthday = null,
        #[MapFromKey('blood_type')]
        public readonly ?string $bloodType = null,
        public readonly ?string $hobby = null,
        public readonly ?string $prefectures = null,
        #[MapFromKey('imageURL')]
        public readonly ?ActressImageUrl $imageUrl = null,
        #[MapFromKey('listURL')]
        public readonly ?ActressListUrl $listUrl = null,
    ) {
    }
}
