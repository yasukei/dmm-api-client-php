<?php

declare(strict_types=1);

namespace DmmApiClient\Request;

/**
 * 女優検索 API の並び順（`sort` パラメータ）。
 *
 * `-` 始まりのケースは降順を表す。
 */
enum ActressSearchSort: string
{
    case Name = 'name';
    case NameDesc = '-name';
    case Bust = 'bust';
    case BustDesc = '-bust';
    case Waist = 'waist';
    case WaistDesc = '-waist';
    case Hip = 'hip';
    case HipDesc = '-hip';
    case Height = 'height';
    case HeightDesc = '-height';
    case Birthday = 'birthday';
    case BirthdayDesc = '-birthday';
    case Id = 'id';
    case IdDesc = '-id';
}
