<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ItemList;

use CuyZ\Valinor\Mapper\Configurator\MapFromKey;

/**
 * サイズ別のサンプル画像。
 *
 * どちらのサイズも、返らないフロアではキーごと落ちる。同人のフロアは
 * `sample_l` だけを返し、通販やゲームのフロアは `sample_s` だけを返す。
 */
final readonly class SampleImageUrl
{
    /**
     * @param SampleImages|null $sampleS 小サイズのサンプル画像
     * @param SampleImages|null $sampleL 大サイズのサンプル画像
     */
    public function __construct(
        #[MapFromKey('sample_s')]
        public ?SampleImages $sampleS = null,
        #[MapFromKey('sample_l')]
        public ?SampleImages $sampleL = null,
    ) {
    }
}
