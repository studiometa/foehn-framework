<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Tests\Fixtures\ContextProviderFixture;
use Tests\Fixtures\InvalidContextProviderFixture;
use Tests\Fixtures\NoAttributeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ContextProviderDiscovery();
});

describe('ContextProviderDiscovery', function () {
    it('discovers context provider attributes on classes', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ContextProviderFixture::class));

        $items = $this->discovery->getItems()->all();

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(ContextProviderFixture::class);
        expect($items[0]['templates'])->toBe(['single', 'page']);
        expect($items[0]['priority'])->toBe(5);
    });

    it('ignores classes without context provider attribute', function () {
        $this->discovery->discover($this->location, new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems()->isEmpty())->toBeTrue();
    });

    it('throws when class does not implement ContextProviderInterface', function () {
        expect(fn() => $this->discovery->discover($this->location, new ReflectionClass(InvalidContextProviderFixture::class)))
            ->toThrow(InvalidArgumentException::class, 'must implement');
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover($this->location, new ReflectionClass(ContextProviderFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
