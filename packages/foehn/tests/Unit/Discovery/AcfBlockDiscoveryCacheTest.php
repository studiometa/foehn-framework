<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfBlock;
use Studiometa\Foehn\Discovery\AcfBlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\AcfBlockFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new AcfBlockDiscovery();
});

describe('AcfBlockDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, AcfBlockFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, AcfBlockFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new AcfBlockDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, AcfBlockFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new AcfBlockDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsAcfBlock::class)
            ->and($item['attribute']->name)
            ->toBe('testimonial')
            ->and($item['attribute']->title)
            ->toBe('Testimonial')
            ->and($item['attribute']->category)
            ->toBe('formatting')
            ->and($item['attribute']->keywords)
            ->toBe(['quote', 'testimonial'])
            ->and($item['className'])
            ->toBe(AcfBlockFixture::class);
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, AcfBlockFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });
});
