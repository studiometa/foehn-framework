<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Tests\Fixtures\ContextProviderFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new ContextProviderDiscovery();
});

describe('ContextProviderDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, ContextProviderFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, ContextProviderFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new ContextProviderDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, ContextProviderFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ContextProviderDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsContextProvider::class)
            ->and($item['attribute']->getTemplates())
            ->toBe(['single', 'page'])
            ->and($item['attribute']->priority)
            ->toBe(5)
            ->and($item['className'])
            ->toBe(ContextProviderFixture::class);
    });
});
