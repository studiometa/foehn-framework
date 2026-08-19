<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Discovery\BlockPatternDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\BlockPatternFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new BlockPatternDiscovery();
});

describe('BlockPatternDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new BlockPatternDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new BlockPatternDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsBlockPattern::class)
            ->and($item['attribute']->name)
            ->toBe('test/hero-pattern')
            ->and($item['attribute']->title)
            ->toBe('Hero Pattern')
            ->and($item['attribute']->categories)
            ->toBe(['featured'])
            ->and($item['attribute']->inserter)
            ->toBeTrue()
            ->and($item['implementsInterface'])
            ->toBeTrue();
    });

    it('reports it was not restored when it scanned', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();
    });

    it('resolves the same template path whether scanned or restored', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        $scanned = $this->discovery->getItems()->all()[0]['attribute']->getTemplatePath();
        $restored = restoreThroughCacheFile(
            $this->discovery,
            new BlockPatternDiscovery(),
        )->getItems()->all()[0]['attribute']->getTemplatePath();

        expect($restored)->toBe($scanned);
    });
});
