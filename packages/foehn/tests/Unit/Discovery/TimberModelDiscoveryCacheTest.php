<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Tests\Fixtures\TimberModelPostFixture;
use Tests\Fixtures\TimberModelTermFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TimberModelDiscovery();
});

describe('TimberModelDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, TimberModelPostFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, TimberModelPostFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new TimberModelDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, TimberModelPostFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new TimberModelDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsTimberModel::class)
            ->and($item['attribute']->name)
            ->toBe('post')
            ->and($item['type'])
            ->toBe('post')
            ->and($item['className'])
            ->toBe(TimberModelPostFixture::class);
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, TimberModelPostFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });

    it('keeps the term type of a term model', function () {
        discoverFixture($this->discovery, TimberModelTermFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new TimberModelDiscovery())->getItems()->all()[0];

        expect($item['type'])->toBe('term')->and($item['attribute']->name)->toBe('category');
    });
});
