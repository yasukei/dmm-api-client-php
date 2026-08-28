<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * サイズ別のサンプル画像。
 */
final readonly class SampleImageUrl
{
    /**
     * @param SampleImages      $sampleS 小サイズのサンプル画像
     * @param SampleImages|null $sampleL 大サイズのサンプル画像
     */
    public function __construct(
        #[MapFromKey('sample_s')]
        public SampleImages $sampleS,
        #[MapFromKey('sample_l')]
        public ?SampleImages $sampleL = null,
    ) {
    }
}
