<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockDiscovery;
use Tests\Fixtures\BlockFixture;
use Tests\Fixtures\InvalidBlockFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new BlockDiscovery();
});

describe('BlockDiscovery', function () {
    it('discovers block attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(BlockFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(BlockFixture::class);
        expect($items[0]['attribute']->name)->toBe('test/hero');
        expect($items[0]['attribute']->title)->toBe('Hero Block');
        expect($items[0]['attribute']->category)->toBe('design');
        expect($items[0]['attribute']->icon)->toBe('cover-image');
        expect($items[0]['attribute']->description)->toBe('A hero block.');
        expect($items[0]['attribute']->keywords)->toBe(['hero', 'banner']);
    });

    it('ignores classes without block attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('throws when class does not implement BlockInterface', function () {
        expect(fn() => $this->discovery->discover(new ReflectionClass(InvalidBlockFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(BlockFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
