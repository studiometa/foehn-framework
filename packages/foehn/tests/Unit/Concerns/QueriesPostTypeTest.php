<?php

declare(strict_types=1);

use Studiometa\Foehn\PostTypes\PostTypeRegistry;
use Studiometa\Foehn\Query\PostQueryBuilder;
use Tests\Fixtures\ProductFixture;

beforeEach(function () {
    PostTypeRegistry::clear();
    PostTypeRegistry::register(ProductFixture::class, 'product');
});

describe('QueriesPostType', function () {
    it('creates a query builder scoped to the post type', function () {
        $builder = ProductFixture::query();

        expect($builder)->toBeInstanceOf(PostQueryBuilder::class);

        $params = $builder->getParameters();

        expect($params['post_type'])->toBe('product');
        expect($params['post_status'])->toBe('publish');
    });

    it('supports fluent method chaining', function () {
        $builder = ProductFixture::query()
            ->limit(10)
            ->orderBy('date', 'DESC')
            ->whereTax('category', 'featured');

        $params = $builder->getParameters();

        expect($params['posts_per_page'])->toBe(10);
        expect($params['orderby'])->toBe('date');
        expect($params['order'])->toBe('DESC');
        expect($params['tax_query'])->toBeArray();
    });

    it('throws when class is not registered', function () {
        PostTypeRegistry::clear();

        expect(fn() => ProductFixture::query())
            ->toThrow(RuntimeException::class, 'is not registered');
    });
});

describe('QueriesPostType::all', function () {
    it('creates a query for all posts with limit', function () {
        // We can't easily test the actual execution without WordPress,
        // but we can verify the trait method exists and is callable
        expect(method_exists(ProductFixture::class, 'all'))->toBeTrue();
    });
});

describe('QueriesPostType::find', function () {
    it('method exists and is callable', function () {
        expect(method_exists(ProductFixture::class, 'find'))->toBeTrue();
    });
});

describe('QueriesPostType::first', function () {
    it('method exists and is callable', function () {
        expect(method_exists(ProductFixture::class, 'first'))->toBeTrue();
    });
});

describe('QueriesPostType::count', function () {
    it('method exists and is callable', function () {
        expect(method_exists(ProductFixture::class, 'count'))->toBeTrue();
    });
});

describe('QueriesPostType::exists', function () {
    it('method exists and is callable', function () {
        expect(method_exists(ProductFixture::class, 'exists'))->toBeTrue();
    });
});
