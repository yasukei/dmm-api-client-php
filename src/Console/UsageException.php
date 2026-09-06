<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\Exception\DmmApiClientException;
use RuntimeException;

/**
 * コマンドラインの指定に誤りがあることを表す例外。
 *
 * ライブラリの API からは投げられず、`bin/dmm-api-client` の引数解釈だけが使う。
 * それでも {@see DmmApiClientException} を実装するのは、コマンドが送出しうる例外を
 * 1 つの catch でまとめて受けられるようにするため。
 */
final class UsageException extends RuntimeException implements DmmApiClientException
{
}
