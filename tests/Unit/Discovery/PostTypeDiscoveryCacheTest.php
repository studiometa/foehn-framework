<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\Discovery\PostTypeDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->discovery = new PostTypeDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
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

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\PostTypes\\Product',
            'implementsConfig' => true,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('product');
        expect($cacheableData[0]['singular'])->toBe('Product');
        expect($cacheableData[0]['plural'])->toBe('Products');
        expect($cacheableData[0]['public'])->toBeTrue();
        expect($cacheableData[0]['hasArchive'])->toBeTrue();
        expect($cacheableData[0]['showInRest'])->toBeTrue();
        expect($cacheableData[0]['menuIcon'])->toBe('dashicons-cart');
        expect($cacheableData[0]['supports'])->toBe(['title', 'editor']);
        expect($cacheableData[0]['taxonomies'])->toBe(['product_cat']);
        expect($cacheableData[0]['rewriteSlug'])->toBe('products');
        expect($cacheableData[0]['className'])->toBe('App\\PostTypes\\Product');
        expect($cacheableData[0]['implementsConfig'])->toBeTrue();
    });

    it('handles minimal attribute configuration', function () {
        $attribute = new AsPostType(
            name: 'event',
            singular: 'Event',
            plural: 'Events',
        );

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'className' => 'App\\PostTypes\\Event',
            'implementsConfig' => false,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['name'])->toBe('event');
        expect($cacheableData[0]['public'])->toBeTrue();
        expect($cacheableData[0]['hasArchive'])->toBeFalse();
        expect($cacheableData[0]['implementsConfig'])->toBeFalse();
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
                'className' => 'App\\PostTypes\\Product',
                'implementsConfig' => false,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles multiple post types', function () {
        $this->discovery->getItems()->add($this->location, [
            'attribute' => new AsPostType('product', 'Product', 'Products'),
            'className' => 'App\\PostTypes\\Product',
            'implementsConfig' => false,
        ]);

        $this->discovery->getItems()->add($this->location, [
            'attribute' => new AsPostType('event', 'Event', 'Events'),
            'className' => 'App\\PostTypes\\Event',
            'implementsConfig' => true,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0]['name'])->toBe('product');
        expect($cacheableData[1]['name'])->toBe('event');
    });
});
