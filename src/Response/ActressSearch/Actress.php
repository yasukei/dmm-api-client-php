<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;
use DateTimeImmutable;

/**
 * 女優情報 1 件。
 */
final readonly class Actress
{
    /**
     * @param string                 $id          女優 ID（例: "12345"）
     * @param string                 $name        女優名
     * @param string                 $ruby        女優名かな
     * @param string|null            $bust        バスト（cm）
     * @param string|null            $cup         カップ数（例: G）
     * @param string|null            $waist       ウエスト（cm）
     * @param string|null            $hip         ヒップ（cm）
     * @param string|null            $height      身長（cm）
     * @param DateTimeImmutable|null $birthday    生年月日
     * @param string|null            $bloodType   血液型（例: A）
     * @param string|null            $hobby       趣味
     * @param string|null            $prefectures 出身地（例: 東京都）
     * @param ActressImageUrl|null   $imageUrl    プロフィール画像
     * @param ActressListUrl|null    $listUrl     出演作品一覧へのリンク
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $ruby,
        public ?string $bust = null,
        public ?string $cup = null,
        public ?string $waist = null,
        public ?string $hip = null,
        public ?string $height = null,
        public ?DateTimeImmutable $birthday = null,
        #[MapFromKey('blood_type')]
        public ?string $bloodType = null,
        public ?string $hobby = null,
        public ?string $prefectures = null,
        #[MapFromKey('imageURL')]
        public ?ActressImageUrl $imageUrl = null,
        #[MapFromKey('listURL')]
        public ?ActressListUrl $listUrl = null,
    ) {
    }
}
