<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Tests\Fixtures\ContextProviderFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    wp_stub_reset();
    $container = bootTestContainer();

    // Register a ContextProviderRegistry
    $this->registry = new ContextProviderRegistry();
    $container->singleton(ContextProviderRegistry::class, fn() => $this->registry);

    $this->discovery = new ContextProviderDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('ContextProviderDiscovery apply', function () {
    it('registers discovered context providers with the registry', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(ContextProviderFixture::class),
        );
        $this->discovery->apply();

        expect($this->registry->count())->toBe(2); // 'single' and 'page'
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect($this->registry->count())->toBe(0);
    });

    it('registers the same providers whether scanned or restored from cache', function () {
        $scanned = new ContextProviderDiscovery();
        discoverFixture($scanned, ContextProviderFixture::class, $this->location);

        restoreThroughCacheFile($scanned, $this->discovery)->apply();

        expect($this->registry->count())->toBe(2); // 'single' and 'page'
    });
});
