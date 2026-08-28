<?php

declare(strict_types=1);

namespace DmmApiClient;

/**
 * DMM のサイト区分。
 *
 * リクエストの `site` パラメータと、レスポンスの `site_code` の双方で使用する。
 * 未知のサイトコードが返った場合はマッピングエラーとして検知する。
 */
enum SiteCode: string
{
    /** DMM.com（一般） */
    case DmmCom = 'DMM.com';

    /** FANZA（アダルト） */
    case Fanza = 'FANZA';
}
