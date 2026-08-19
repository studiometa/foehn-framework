<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsImageSize;
use Studiometa\Foehn\Discovery\ImageSizeDiscovery;
use Tests\Fixtures\ImageSizeFixture;
use Tests\Fixtures\ImageSizeWithNameFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new ImageSizeDiscovery();
});

describe('ImageSizeDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, ImageSizeFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, ImageSizeFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new ImageSizeDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, ImageSizeFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ImageSizeDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

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

    it('restores an explicit name', function () {
        discoverFixture($this->discovery, ImageSizeWithNameFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new ImageSizeDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute']->name)->toBe('hero_banner');
    });
});
