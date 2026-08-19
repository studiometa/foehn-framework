<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Tests\Fixtures\TimberModelPostFixture;
use Tests\Fixtures\TimberModelTermFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new TimberModelDiscovery();
});

describe('TimberModelDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, TimberModelPostFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, TimberModelPostFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new TimberModelDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, TimberModelPostFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new TimberModelDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsTimberModel::class)
            ->and($item['attribute']->name)
            ->toBe('post')
            ->and($item['type'])
            ->toBe('post')
            ->and($item['className'])
            ->toBe(TimberModelPostFixture::class);
    });

    it('keeps the term type of a term model', function () {
        discoverFixture($this->discovery, TimberModelTermFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new TimberModelDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['type'])->toBe('term')->and($item['attribute']->name)->toBe('category');
    });
});
