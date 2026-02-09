<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tests\Fixtures\MenuFixture;
use Tests\Fixtures\NoAttributeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new MenuDiscovery();
});

describe('MenuDiscovery', function () {
    it('discovers menu attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(MenuFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(MenuFixture::class);
        expect($items[0]['attribute']->location)->toBe('primary');
        expect($items[0]['attribute']->description)->toBe('Primary Navigation');
    });

    it('ignores classes without menu attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(MenuFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });

    it('can be cached and restored', function () {
        $this->discovery->discover($this->location, new ReflectionClass(MenuFixture::class));

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveCount(1);
        expect($cacheData[0]['location'])->toBe('primary');
        expect($cacheData[0]['description'])->toBe('Primary Navigation');
        expect($cacheData[0]['className'])->toBe(MenuFixture::class);

        // Restore from cache
        $newDiscovery = new MenuDiscovery();
        $newDiscovery->restoreFromCache($cacheData);

        expect($newDiscovery->wasRestoredFromCache())->toBeTrue();
    });
});
