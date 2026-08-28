<?php

declare(strict_types=1);

use DmmApiClient\Exception\InvalidArgumentException;
use DmmApiClient\Request\ArticleFilter;
use DmmApiClient\Request\ArticleType;

test('種別と ID を保持する', function (): void {
    $filter = new ArticleFilter(ArticleType::Maker, '45276');

    expect($filter->type)->toBe(ArticleType::Maker)
        ->and($filter->id)->toBe('45276');
});

test('ID が空なら拒否する', function (): void {
    expect(fn (): ArticleFilter => new ArticleFilter(ArticleType::Genre, ''))
        ->toThrow(InvalidArgumentException::class, 'article_id must not be empty.');
});
