<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockPattern;
use Studiometa\Foehn\Discovery\BlockPatternDiscovery;
use Tests\Fixtures\BlockPatternFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new BlockPatternDiscovery();
});

describe('BlockPatternDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new BlockPatternDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new BlockPatternDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

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

    it('resolves the same template path whether scanned or restored', function () {
        discoverFixture($this->discovery, BlockPatternFixture::class, $this->location);

        $scanned = iterator_to_array($this->discovery->getItems())[0]['attribute']->getTemplatePath();
        $restored = restoreThroughCacheFile($this->discovery, new BlockPatternDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0]['attribute']->getTemplatePath();

        expect($restored)->toBe($scanned);
    });
});
