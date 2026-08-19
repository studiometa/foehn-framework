<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\ContextProviderFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ContextProviderDiscovery();
});

describe('ContextProviderDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, ContextProviderFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, ContextProviderFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new ContextProviderDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, ContextProviderFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ContextProviderDiscovery())->getItems()->all()[0];

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
