<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockPatternDiscovery;
use Tests\Fixtures\BlockPatternFixture;
use Tests\Fixtures\NoAttributeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new BlockPatternDiscovery();
});

describe('BlockPatternDiscovery', function () {
    it('discovers block pattern attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockPatternFixture::class));

        $items = $this->discovery->getItems()->all();

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
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(BlockPatternFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
