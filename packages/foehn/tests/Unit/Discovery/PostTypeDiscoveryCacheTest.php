<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new PostTypeDiscovery();
});

describe('PostTypeDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsPostType(
            name: 'product',
            singular: 'Product',
            plural: 'Products',
            public: true,
            hasArchive: true,
            showInRest: true,
            menuIcon: 'dashicons-cart',
            supports: ['title', 'editor'],
            taxonomies: ['product_cat'],
            rewriteSlug: 'products',
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\PostTypes\\Product',
            'implementsConfig' => true,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0]['name'])->toBe('product');
        expect($cacheableData['App\\'][0]['singular'])->toBe('Product');
        expect($cacheableData['App\\'][0]['plural'])->toBe('Products');
        expect($cacheableData['App\\'][0]['public'])->toBeTrue();
        expect($cacheableData['App\\'][0]['hasArchive'])->toBeTrue();
        expect($cacheableData['App\\'][0]['showInRest'])->toBeTrue();
        expect($cacheableData['App\\'][0]['menuIcon'])->toBe('dashicons-cart');
        expect($cacheableData['App\\'][0]['supports'])->toBe(['title', 'editor']);
        expect($cacheableData['App\\'][0]['taxonomies'])->toBe(['product_cat']);
        expect($cacheableData['App\\'][0]['rewriteSlug'])->toBe('products');
        expect($cacheableData['App\\'][0]['className'])->toBe('App\\PostTypes\\Product');
        expect($cacheableData['App\\'][0]['implementsConfig'])->toBeTrue();
    });

    it('handles minimal attribute configuration', function () {
        $attribute = new AsPostType(name: 'event', singular: 'Event', plural: 'Events');

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\PostTypes\\Event',
            'implementsConfig' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0]['name'])->toBe('event');
        expect($cacheableData['App\\'][0]['public'])->toBeTrue();
        expect($cacheableData['App\\'][0]['hasArchive'])->toBeFalse();
        expect($cacheableData['App\\'][0]['implementsConfig'])->toBeFalse();
    });

    it('includes new WordPress parameters in cache', function () {
        $attribute = new AsPostType(
            name: 'page_like',
            singular: 'Page Like',
            plural: 'Page Likes',
            hierarchical: true,
            menuPosition: 25,
            labels: ['menu_name' => 'Custom Menu'],
            rewrite: ['slug' => 'custom', 'with_front' => false],
        );

        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'attribute' => $attribute,
            'className' => 'App\\PostTypes\\PageLike',
            'implementsConfig' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['hierarchical'])->toBeTrue();
        expect($cacheableData['App\\'][0]['menuPosition'])->toBe(25);
        expect($cacheableData['App\\'][0]['labels'])->toBe(['menu_name' => 'Custom Menu']);
        expect($cacheableData['App\\'][0]['rewrite'])->toBe(['slug' => 'custom', 'with_front' => false]);
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'name' => 'product',
                'singular' => 'Product',
                'plural' => 'Products',
                'public' => true,
                'hasArchive' => true,
                'showInRest' => true,
                'menuIcon' => null,
                'supports' => ['title', 'editor', 'thumbnail'],
                'taxonomies' => [],
                'rewriteSlug' => null,
                'hierarchical' => false,
                'menuPosition' => null,
                'labels' => [],
                'rewrite' => null,
                'className' => 'App\\PostTypes\\Product',
                'implementsConfig' => false,
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles multiple post types', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');

        $ref->invoke($this->discovery, $this->location, [
            'attribute' => new AsPostType('product', 'Product', 'Products'),
            'className' => 'App\\PostTypes\\Product',
            'implementsConfig' => false,
        ]);

        $ref->invoke($this->discovery, $this->location, [
            'attribute' => new AsPostType('event', 'Event', 'Events'),
            'className' => 'App\\PostTypes\\Event',
            'implementsConfig' => true,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(2);
        expect($cacheableData['App\\'][0]['name'])->toBe('product');
        expect($cacheableData['App\\'][1]['name'])->toBe('event');
    });
});
