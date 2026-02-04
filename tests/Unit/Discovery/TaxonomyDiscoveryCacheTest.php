<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsTaxonomy;
use Studiometa\WPTempest\Discovery\TaxonomyDiscovery;

beforeEach(function () {
    $this->discovery = new TaxonomyDiscovery();
});

describe('TaxonomyDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsTaxonomy(
            name: 'product_category',
            postTypes: ['product'],
            singular: 'Category',
            plural: 'Categories',
            public: true,
            hierarchical: true,
            showInRest: true,
            showAdminColumn: true,
            rewriteSlug: 'product-category',
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Taxonomies\\ProductCategory',
            'implementsConfig' => true,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('product_category');
        expect($cacheableData[0]['singular'])->toBe('Category');
        expect($cacheableData[0]['plural'])->toBe('Categories');
        expect($cacheableData[0]['postTypes'])->toBe(['product']);
        expect($cacheableData[0]['hierarchical'])->toBeTrue();
        expect($cacheableData[0]['showInRest'])->toBeTrue();
        expect($cacheableData[0]['rewriteSlug'])->toBe('product-category');
        expect($cacheableData[0]['className'])->toBe('App\\Taxonomies\\ProductCategory');
        expect($cacheableData[0]['implementsConfig'])->toBeTrue();
    });

    it('handles taxonomy with multiple post types', function () {
        $attribute = new AsTaxonomy(
            name: 'tag',
            postTypes: ['product', 'event', 'post'],
            singular: 'Tag',
            plural: 'Tags',
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Taxonomies\\Tag',
            'implementsConfig' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['postTypes'])->toBe(['product', 'event', 'post']);
    });

    it('includes new WordPress parameters in cache', function () {
        $attribute = new AsTaxonomy(
            name: 'genre',
            singular: 'Genre',
            plural: 'Genres',
            labels: ['menu_name' => 'Music Genres'],
            rewrite: false,
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'attribute' => $attribute,
            'className' => 'App\\Taxonomies\\Genre',
            'implementsConfig' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['labels'])->toBe(['menu_name' => 'Music Genres']);
        expect($cacheableData[0]['rewrite'])->toBeFalse();
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'name' => 'product_category',
                'singular' => 'Category',
                'plural' => 'Categories',
                'postTypes' => ['product'],
                'public' => true,
                'hierarchical' => false,
                'showInRest' => true,
                'showAdminColumn' => true,
                'rewriteSlug' => null,
                'labels' => [],
                'rewrite' => null,
                'className' => 'App\\Taxonomies\\ProductCategory',
                'implementsConfig' => false,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
