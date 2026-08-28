<?php

declare(strict_types=1);

namespace DmmApiClient\Response\ActressSearch;

/**
 * サービス別の、女優の出演作品一覧へのアフィリエイトリンク。
 */
final readonly class ActressListUrl
{
    /**
     * @param string $digital 動画（digital）の作品一覧 URL
     * @param string $monthly 月額動画（monthly）の作品一覧 URL
     * @param string $mono    通販（mono）の作品一覧 URL
     */
    public function __construct(
        public string $digital,
        public string $monthly,
        public string $mono,
    ) {
    }
}
