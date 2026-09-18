<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Api\DmmApiClient;
use DmmApiClient\Api\MonoStock;
use DmmApiClient\Api\Request\ArticleFilter;
use DmmApiClient\Api\Request\ArticleType;
use DmmApiClient\Api\Request\ItemListRequest;
use DmmApiClient\Api\Request\ItemListSort;

/**
 * 商品情報 API (`/ItemList`) を呼び出す。
 */
final class ItemListCommand extends ApiCommand
{
    public function name(): string
    {
        return 'item-list';
    }

    public function description(): string
    {
        return '商品を検索する (/ItemList)';
    }

    protected function requestOptions(): array
    {
        return [
            new OptionDefinition('site', '検索対象サイト（必須。例: DMM.com、FANZA）', 'CODE'),
            new OptionDefinition('service', 'サービスコードで絞り込む（例: digital）', 'CODE'),
            new OptionDefinition('floor', 'フロアコードで絞り込む（例: videoa）', 'CODE'),
            new OptionDefinition('keyword', '検索キーワード', 'WORD'),
            new OptionDefinition('cid', '商品 ID を指定して 1 件だけ取得する', 'ID'),
            new OptionDefinition('article', 'カテゴリで絞り込む（' . self::allowedValues(ArticleType::class) . '）。複数指定可', 'TYPE'),
            new OptionDefinition('article-id', '--article に対応する ID。--article と同じ回数だけ指定する', 'ID'),
            new OptionDefinition('gte-date', 'この日時以降に発売・配信された商品に絞り込む', self::DATETIME_PLACEHOLDER),
            new OptionDefinition('lte-date', 'この日時以前に発売・配信された商品に絞り込む', self::DATETIME_PLACEHOLDER),
            new OptionDefinition('mono-stock', '通販商品の在庫で絞り込む（' . self::allowedValues(MonoStock::class) . '）', 'VALUE'),
            new OptionDefinition('sort', '並び順（' . self::allowedValues(ItemListSort::class) . '）', 'ORDER'),
            new OptionDefinition('hits', '取得件数（1〜100）', 'N'),
            new OptionDefinition('offset', '検索開始位置（1〜50000）', 'N'),
        ];
    }

    protected function createRequest(Input $input): ItemListRequest
    {
        return new ItemListRequest(
            site: $this->requiredOption($input, 'site'),
            service: $input->option('service'),
            floor: $input->option('floor'),
            keyword: $input->option('keyword'),
            cid: $input->option('cid'),
            articles: $this->articleFilters($input),
            gteDate: $this->dateOption($input, 'gte-date'),
            lteDate: $this->dateOption($input, 'lte-date', endOfDay: true),
            monoStock: $this->enumOption($input, 'mono-stock', MonoStock::class),
            sort: $this->enumOption($input, 'sort', ItemListSort::class),
            hits: $this->intOption($input, 'hits'),
            offset: $this->intOption($input, 'offset'),
        );
    }

    protected function invoke(DmmApiClient $client, Input $input): object
    {
        return $client->itemList($this->createRequest($input));
    }

    /**
     * `--article` と `--article-id` の組を、指定された順に対応づける。
     *
     * @return list<ArticleFilter>
     *
     * @throws UsageException 指定回数が揃っていない、または未知のカテゴリの場合
     */
    private function articleFilters(Input $input): array
    {
        $types = $input->optionValues('article');
        $ids = $input->optionValues('article-id');

        if (count($types) !== count($ids)) {
            throw new UsageException(sprintf(
                '--article and --article-id must be given the same number of times (%d vs %d).',
                count($types),
                count($ids),
            ));
        }

        $filters = [];

        foreach ($types as $index => $type) {
            $filters[] = new ArticleFilter(self::toEnum($type, 'article', ArticleType::class), $ids[$index]);
        }

        return $filters;
    }
}
