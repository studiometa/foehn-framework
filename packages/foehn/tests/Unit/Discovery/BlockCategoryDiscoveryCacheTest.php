<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockCategory;
use Studiometa\Foehn\Discovery\BlockCategoryDiscovery;
use Tests\Fixtures\BlockCategoryFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new BlockCategoryDiscovery();
});

describe('BlockCategoryDiscovery caching', function () {
    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, BlockCategoryFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new BlockCategoryDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, BlockCategoryFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new BlockCategoryDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsBlockCategory::class)
            ->and($item['attribute']->slug)
            ->toBe('acme-layout')
            ->and($item['attribute']->icon)
            ->toBe('layout');
    });
});
