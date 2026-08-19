<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockPatternDiscovery;
use Tests\Fixtures\BlockPatternFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new BlockPatternDiscovery();
});

describe('BlockPatternDiscovery', function () {
    it('discovers block pattern attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(BlockPatternFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(BlockPatternFixture::class);
        expect($items[0]['attribute']->name)->toBe('test/hero-pattern');
        expect($items[0]['attribute']->title)->toBe('Hero Pattern');
        expect($items[0]['attribute']->categories)->toBe(['featured']);
        expect($items[0]['attribute']->keywords)->toBe(['hero']);
        expect($items[0]['attribute']->description)->toBe('A hero pattern.');
        expect($items[0]['implementsInterface'])->toBeTrue();
    });

    it('ignores classes without block pattern attribute', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(BlockPatternFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
