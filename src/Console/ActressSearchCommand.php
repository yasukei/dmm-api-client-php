<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Request\ActressSearchRequest;
use DmmApiClient\Request\ActressSearchSort;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\ActressSearch\ActressSearchResponse;

/**
 * 女優検索 API (`/ActressSearch`) を呼び出す。
 */
final class ActressSearchCommand extends ApiCommand
{
    public function name(): string
    {
        return 'actress-search';
    }

    public function description(): string
    {
        return '女優を検索する (/ActressSearch)';
    }

    protected function requestOptions(): array
    {
        return [
            new OptionDefinition('initial', '女優名かなの前方一致（2 文字以上も指定できる。例: あ、あさ）', 'KANA'),
            new OptionDefinition('actress-id', '女優 ID を指定して 1 件だけ取得する', 'ID'),
            new OptionDefinition('keyword', '女優名の検索キーワード', 'WORD'),
            new OptionDefinition('gte-bust', 'バストがこの値以上（cm）', 'N'),
            new OptionDefinition('lte-bust', 'バストがこの値以下（cm）', 'N'),
            new OptionDefinition('gte-waist', 'ウエストがこの値以上（cm）', 'N'),
            new OptionDefinition('lte-waist', 'ウエストがこの値以下（cm）', 'N'),
            new OptionDefinition('gte-hip', 'ヒップがこの値以上（cm）', 'N'),
            new OptionDefinition('lte-hip', 'ヒップがこの値以下（cm）', 'N'),
            new OptionDefinition('gte-height', '身長がこの値以上（cm）', 'N'),
            new OptionDefinition('lte-height', '身長がこの値以下（cm）', 'N'),
            new OptionDefinition('gte-birthday', '生年月日がこの日以降', 'DATE'),
            new OptionDefinition('lte-birthday', '生年月日がこの日以前', 'DATE'),
            new OptionDefinition('sort', '並び順（' . self::allowedValues(ActressSearchSort::class) . '）', 'ORDER'),
            new OptionDefinition('hits', '取得件数（1〜100）', 'N'),
            new OptionDefinition('offset', '検索開始位置（1 以上）', 'N'),
        ];
    }

    protected function endpoint(): string
    {
        return ActressSearchRequest::ENDPOINT;
    }

    protected function createRequest(Input $input): Request
    {
        return new ActressSearchRequest(
            initial: $input->option('initial'),
            actressId: $input->option('actress-id'),
            keyword: $input->option('keyword'),
            gteBust: $this->intOption($input, 'gte-bust'),
            lteBust: $this->intOption($input, 'lte-bust'),
            gteWaist: $this->intOption($input, 'gte-waist'),
            lteWaist: $this->intOption($input, 'lte-waist'),
            gteHip: $this->intOption($input, 'gte-hip'),
            lteHip: $this->intOption($input, 'lte-hip'),
            gteHeight: $this->intOption($input, 'gte-height'),
            lteHeight: $this->intOption($input, 'lte-height'),
            gteBirthday: $this->dateOption($input, 'gte-birthday'),
            lteBirthday: $this->dateOption($input, 'lte-birthday'),
            sort: $this->enumOption($input, 'sort', ActressSearchSort::class),
            hits: $this->intOption($input, 'hits'),
            offset: $this->intOption($input, 'offset'),
        );
    }

    protected function responseClass(): string
    {
        return ActressSearchResponse::class;
    }
}
