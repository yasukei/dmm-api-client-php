<?php

declare(strict_types=1);

namespace DmmApiClient\Console;

use DmmApiClient\Exception\UsageException;
use DmmApiClient\Request\ArticleFilter;
use DmmApiClient\Request\ArticleType;
use DmmApiClient\Request\ItemListRequest;
use DmmApiClient\Request\ItemListSort;
use DmmApiClient\Request\MonoStock;
use DmmApiClient\Request\Request;
use DmmApiClient\Response\ItemList\ItemListResponse;
use DmmApiClient\SiteCode;

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
            new OptionDefinition('site', '検索対象サイト（必須。' . self::allowedValues(SiteCode::class) . '）', 'CODE'),
            new OptionDefinition('service', 'サービスコードで絞り込む（例: digital）', 'CODE'),
            new OptionDefinition('floor', 'フロアコードで絞り込む（例: videoa）', 'CODE'),
            new OptionDefinition('keyword', '検索キーワード', 'WORD'),
            new OptionDefinition('cid', '商品 ID を指定して 1 件だけ取得する', 'ID'),
            new OptionDefinition('article', 'カテゴリで絞り込む（' . self::allowedValues(ArticleType::class) . '）。複数指定可', 'TYPE'),
            new OptionDefinition('article-id', '--article に対応する ID。--article と同じ回数だけ指定する', 'ID'),
            new OptionDefinition('gte-date', 'この日時以降に発売・配信された商品に絞り込む', 'DATE'),
            new OptionDefinition('lte-date', 'この日時以前に発売・配信された商品に絞り込む', 'DATE'),
            new OptionDefinition('mono-stock', '通販商品の在庫で絞り込む（' . self::allowedValues(MonoStock::class) . '）', 'VALUE'),
            new OptionDefinition('sort', '並び順（' . self::allowedValues(ItemListSort::class) . '）', 'ORDER'),
            new OptionDefinition('hits', '取得件数（1〜100）', 'N'),
            new OptionDefinition('offset', '検索開始位置（1〜50000）', 'N'),
        ];
    }

    protected function endpoint(): string
    {
        return ItemListRequest::ENDPOINT;
    }

    protected function createRequest(Input $input): Request
    {
        $site = SiteCode::tryFrom($this->requiredOption($input, 'site'))
            ?? throw new UsageException(sprintf(
                'Invalid value for --site. Expected one of: %s.',
                self::allowedValues(SiteCode::class),
            ));

        return new ItemListRequest(
            site: $site,
            service: $input->option('service'),
            floor: $input->option('floor'),
            keyword: $input->option('keyword'),
            cid: $input->option('cid'),
            articles: $this->articleFilters($input),
            gteDate: $this->dateOption($input, 'gte-date'),
            lteDate: $this->dateOption($input, 'lte-date'),
            monoStock: $this->enumOption($input, 'mono-stock', MonoStock::class),
            sort: $this->enumOption($input, 'sort', ItemListSort::class),
            hits: $this->intOption($input, 'hits'),
            offset: $this->intOption($input, 'offset'),
        );
    }

    protected function responseClass(): string
    {
        return ItemListResponse::class;
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
            $articleType = ArticleType::tryFrom($type) ?? throw new UsageException(sprintf(
                'Invalid value "%s" for --article. Expected one of: %s.',
                $type,
                self::allowedValues(ArticleType::class),
            ));

            $filters[] = new ArticleFilter($articleType, $ids[$index]);
        }

        return $filters;
    }
}
