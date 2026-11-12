<?php

declare(strict_types=1);

use Studiometa\Foehn\Inspect\ContextKeys;
use Tests\Fixtures\InspectApp\BrandController;
use Tests\Fixtures\InspectApp\OutletController;
use Tests\Fixtures\InspectApp\ProductController;
use Tests\Fixtures\InspectApp\SharedMeta;

describe('ContextKeys', function () {
    it('gives every render the TemplateContext and Timber global keys', function () {
        expect(ContextKeys::render(hasMenus: false))->toBe([
            'body_class',
            'http_host',
            'post',
            'posts',
            'site',
            'theme',
            'user',
            'wp_title',
        ]);
        expect(ContextKeys::render(hasMenus: true))->toContain('menus');
    });

    it('adds what Timber::context() sets for the request a template answers', function (
        string $template,
        array $added,
    ) {
        expect(ContextKeys::request($template))->toBe($added);
    })->with([
        ['category', ['term']],
        ['category-news', ['term']],
        ['tag-*', ['term']],
        ['taxonomy-brand', ['term']],
        ['search', ['search_query']],
        ['author', ['author']],
        ['single-product', []],
        // A wildcard that reaches several kinds of request promises none of their keys.
        ['*', []],
        ['archive-*', []],
        ['categoryfoo', []],
    ]);

    it('reads the keys of literal with() calls, and none from comments', function () {
        expect(ContextKeys::declaredBy(ProductController::class))->toBe([
            'keys' => ['in_stock', 'related'],
            'partial' => false,
        ]);
    });

    it('calls a class partial when it sets keys only known at runtime', function () {
        expect(ContextKeys::declaredBy(BrandController::class))->toBe(['keys' => ['brand_count'], 'partial' => true]);
    });

    it('reads the keys a parent class and its traits add', function () {
        expect(ContextKeys::declaredBy(OutletController::class))->toBe([
            'keys' => ['breadcrumbs', 'outlet', 'shop_name'],
            'partial' => false,
        ]);
    });

    it('finds nothing in a class that adds no keys', function () {
        expect(ContextKeys::declaredBy(SharedMeta::class))->toBe(['keys' => [], 'partial' => false]);
    });
});
