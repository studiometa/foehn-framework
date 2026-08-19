<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\ImageSizeDiscovery;
use Tests\Fixtures\ImageSizeFixture;
use Tests\Fixtures\ImageSizeWithNameFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ImageSizeDiscovery();
});

describe('ImageSizeDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, ImageSizeFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, ImageSizeFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new ImageSizeDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, ImageSizeFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ImageSizeDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsImageSize::class)
            ->and($item['attribute']->width)
            ->toBe(1200)
            ->and($item['attribute']->height)
            ->toBe(630)
            ->and($item['attribute']->crop)
            ->toBeTrue()
            ->and($item['attribute']->name)
            ->toBeNull();
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, ImageSizeFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });

    it('restores an explicit name', function () {
        discoverFixture($this->discovery, ImageSizeWithNameFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ImageSizeDiscovery())->getItems()->all()[0];

        expect($item['attribute']->name)->toBe('hero_banner');
    });
});
