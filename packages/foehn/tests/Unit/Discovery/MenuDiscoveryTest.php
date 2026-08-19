<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tests\Fixtures\MenuFixture;
use Tests\Fixtures\NoAttributeFixture;

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

        $restored = restoreThroughCacheFile($this->discovery, new MenuDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });
});
