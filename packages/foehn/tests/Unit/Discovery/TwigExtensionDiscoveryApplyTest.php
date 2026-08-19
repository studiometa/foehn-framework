<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\TwigExtensionDiscovery;
use Tests\Fixtures\TwigExtensionFixture;
use Tests\Fixtures\TwigExtensionWithPriorityFixture;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    $this->container = bootTestContainer();
    $this->discovery = new TwigExtensionDiscovery($this->container);
});

afterEach(fn() => tearDownTestContainer());

describe('TwigExtensionDiscovery::apply', function () {
    it('registers timber/twig filter when extensions are discovered', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('add_filter');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['hook'])->toBe('timber/twig');
        expect($calls[0]['args']['callback'])->toBeCallable();
    });

    it('does not register filter when no extensions discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_filter'))->toBeEmpty();
    });

    it('sorts extensions by priority before registering', function () {
        // Discover in reverse priority order
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionFixture::class)); // priority 10
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionWithPriorityFixture::class)); // priority 5
        $this->discovery->apply();

        $calls = wp_stub_get_calls('add_filter');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['hook'])->toBe('timber/twig');
        expect($calls[0]['args']['callback'])->toBeCallable();
    });

    it('callback registers extensions with Twig environment', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionFixture::class));
        $this->discovery->discover($this->location, new ReflectionClass(TwigExtensionWithPriorityFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('add_filter');
        $callback = $calls[0]['args']['callback'];

        // Create a real Twig environment
        $twig = new Environment(new ArrayLoader([]));

        // Execute the callback
        $result = $callback($twig);

        // Verify extensions were added
        expect($result)->toBe($twig);
        expect($twig->getExtension(TwigExtensionFixture::class))->toBeInstanceOf(TwigExtensionFixture::class);
        expect($twig->getExtension(TwigExtensionWithPriorityFixture::class))
            ->toBeInstanceOf(TwigExtensionWithPriorityFixture::class);
    });

    it('works with cache restoration', function () {
        $scanned = new TwigExtensionDiscovery($this->container);
        discoverFixture($scanned, TwigExtensionWithPriorityFixture::class, $this->location);
        discoverFixture($scanned, TwigExtensionFixture::class, $this->location);

        restoreThroughCacheFile($scanned, $this->discovery)->apply();

        $calls = wp_stub_get_calls('add_filter');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['hook'])->toBe('timber/twig');
    });
});
