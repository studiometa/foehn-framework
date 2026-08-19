<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tests\Fixtures\MenuFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new MenuDiscovery();
});

describe('MenuDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, MenuFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores the same attribute through a cache file', function () {
        discoverFixture($this->discovery, MenuFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new MenuDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, MenuFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new MenuDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsMenu::class)
            ->and($item['attribute']->location)
            ->toBe('primary')
            ->and($item['attribute']->description)
            ->toBe('Primary Navigation')
            ->and($item['className'])
            ->toBe(MenuFixture::class);
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, MenuFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });
});
