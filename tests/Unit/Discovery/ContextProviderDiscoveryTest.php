<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Tests\Fixtures\ContextProviderFixture;
use Tests\Fixtures\InvalidContextProviderFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    $this->discovery = new ContextProviderDiscovery();
});

describe('ContextProviderDiscovery', function () {
    it('discovers context provider attributes on classes', function () {
        $this->discovery->discover(new ReflectionClass(ContextProviderFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(ContextProviderFixture::class);
        expect($items[0]['templates'])->toBe(['single', 'page']);
        expect($items[0]['priority'])->toBe(5);
    });

    it('ignores classes without context provider attribute', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('throws when class does not implement ContextProviderInterface', function () {
        expect(fn() => $this->discovery->discover(new ReflectionClass(InvalidContextProviderFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(ContextProviderFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
