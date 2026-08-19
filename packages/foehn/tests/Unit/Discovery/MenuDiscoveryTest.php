<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tests\Fixtures\MenuFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new MenuDiscovery();
});

describe('MenuDiscovery', function () {
    it('discovers menu attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(MenuFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(MenuFixture::class);
        expect($items[0]['attribute']->location)->toBe('primary');
        expect($items[0]['attribute']->description)->toBe('Primary Navigation');
    });

    it('ignores classes without menu attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(MenuFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });

    it('can be cached and restored', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(MenuFixture::class));

        $restored = restoreThroughCacheFile($this->discovery, new MenuDiscovery());

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });
});
