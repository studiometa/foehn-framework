<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsMenu;
use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tests\Fixtures\MenuFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new MenuDiscovery();
});

describe('MenuDiscovery caching', function () {
    it('keeps the item under its location namespace', function () {
        discoverFixture($this->discovery, MenuFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores the same attribute through a cache file', function () {
        discoverFixture($this->discovery, MenuFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new MenuDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, MenuFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new MenuDiscovery())
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsMenu::class)
            ->and($item['attribute']->location)
            ->toBe('primary')
            ->and($item['attribute']->description)
            ->toBe('Primary Navigation')
            ->and($item['className'])
            ->toBe(MenuFixture::class);
    });
});
