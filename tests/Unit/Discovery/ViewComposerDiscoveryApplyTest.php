<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ViewComposerDiscovery;
use Studiometa\Foehn\Views\ViewComposerRegistry;
use Tests\Fixtures\ViewComposerFixture;

beforeEach(function () {
    wp_stub_reset();
    $container = bootTestContainer();

    // Register a ViewComposerRegistry
    $this->registry = new ViewComposerRegistry();
    $container->singleton(ViewComposerRegistry::class, fn() => $this->registry);

    $this->discovery = new ViewComposerDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('ViewComposerDiscovery apply', function () {
    it('registers discovered view composers with the registry', function () {
        $this->discovery->discover(new ReflectionClass(ViewComposerFixture::class));
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
                'className' => ViewComposerFixture::class,
                'priority' => 5,
            ],
        ]);

        $this->discovery->apply();

        expect($this->registry->count())->toBe(2); // 'archive' and 'home'
    });
});
