<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsPostMeta;
use Studiometa\Foehn\Discovery\PostMetaDiscovery;
use Tests\Fixtures\PostMeta\Gallery;
use Tests\Fixtures\PostMeta\Product;

beforeEach(function () {
    wp_stub_reset();

    $this->location = testDiscoveryLocation();
    $this->discovery = new PostMetaDiscovery();
});

describe('PostMetaDiscovery caching', function () {
    it('keeps every item under its location', function () {
        discoverFixture($this->discovery, Product::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(3);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, Product::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new PostMetaDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, Product::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new PostMetaDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])->toBeInstanceOf(AsPostMeta::class);
        expect($item['attribute']->key)->toBe('price');
        expect($item['objectSubtype'])->toBe('product');
    });

    it('survives the round trip with an array schema on it', function () {
        // A schema is a nested array, which is where a cache format that flattens
        // items would lose it.
        discoverFixture($this->discovery, Gallery::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new PostMetaDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute']->schema)->toBe(['items' => ['type' => 'string']]);
    });

    it('registers from the cache without reflecting the class again', function () {
        discoverFixture($this->discovery, Product::class, $this->location);

        restoreThroughCacheFile($this->discovery, new PostMetaDiscovery())->apply();

        $keys = array_column(array_column(wp_stub_get_calls('register_meta'), 'args'), 'metaKey');

        expect($keys)->toBe(['price', 'sku', 'gallery']);
    });
});
