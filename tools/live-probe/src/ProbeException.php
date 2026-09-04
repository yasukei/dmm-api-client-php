<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use RuntimeException;

/**
 * probe の使い方や実行環境に問題があることを表す例外。
 *
 * API のレスポンスに関する失敗はレポートに集約するので、ここには含めない。
 */
final class ProbeException extends RuntimeException
{
}
