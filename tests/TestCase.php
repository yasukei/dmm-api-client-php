<?php

namespace Tests;

use DmmApiClient\Api\Response\ResponseMapper;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * テスト全体で使い回す既定のマッパー。
     *
     * Valinor は DTO の定義をマッパーごとに解析して覚えるため、呼ぶたびに作り直すと
     * 同じ解析を何十回も繰り返すことになる。{@see ResponseMapper} は readonly で
     * 状態を持たないので、使い回してもテスト間で影響し合うことはない。
     */
    private static ?ResponseMapper $responseMapper = null;

    public static function responseMapper(): ResponseMapper
    {
        return self::$responseMapper ??= new ResponseMapper();
    }
}
