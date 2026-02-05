<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ContextProviderDiscovery;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Tests\Fixtures\ContextProviderFixture;

beforeEach(function () {
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
        $this->discovery->discover(new ReflectionClass(ContextProviderFixture::class));
        $this->discovery->apply();

        expect($this->registry->count())->toBe(2); // 'single' and 'page'
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect($this->registry->count())->toBe(0);
    });

    it('registers from cached data', function () {
        $this->discovery->restoreFromCache([
            [
                'templates' => ['archive', 'home'],
                'className' => ContextProviderFixture::class,
                'priority' => 5,
            ],
        ]);

        $this->discovery->apply();

        expect($this->registry->count())->toBe(2); // 'archive' and 'home'
    });
});
