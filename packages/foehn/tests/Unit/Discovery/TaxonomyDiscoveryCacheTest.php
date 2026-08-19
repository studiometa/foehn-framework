<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTaxonomy;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Tests\Fixtures\TaxonomyFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new TaxonomyDiscovery();
});

describe('TaxonomyDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, TaxonomyFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, TaxonomyFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new TaxonomyDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, TaxonomyFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new TaxonomyDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsTaxonomy::class)
            ->and($item['attribute']->name)
            ->toBe('project_category')
            ->and($item['attribute']->postTypes)
            ->toBe(['project'])
            ->and($item['attribute']->singular)
            ->toBe('Category')
            ->and($item['attribute']->hierarchical)
            ->toBeTrue()
            ->and($item['implementsConfig'])
            ->toBeFalse();
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, TaxonomyFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });
});
