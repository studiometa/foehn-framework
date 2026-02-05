<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tests\Fixtures\MenuFixture;

beforeEach(function () {
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new MenuDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('MenuDiscovery apply', function () {
    it('registers discovered menus with WordPress', function () {
        $this->discovery->discover(new ReflectionClass(MenuFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('register_nav_menus');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['locations'])->toBe(['primary' => 'Primary Navigation']);
    });

    it('registers timber/context filter for menus', function () {
        $this->discovery->discover(new ReflectionClass(MenuFixture::class));
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(1);
        expect($filters[0]['args']['hook'])->toBe('timber/context');
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('register_nav_menus'))->toBeEmpty();
        expect(wp_stub_get_calls('add_filter'))->toBeEmpty();
    });

    it('registers menus from cached data', function () {
        $this->discovery->restoreFromCache([
            [
                'location' => 'footer',
                'description' => 'Footer Navigation',
                'className' => MenuFixture::class,
            ],
        ]);

        $this->discovery->apply();

        $calls = wp_stub_get_calls('register_nav_menus');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['locations'])->toBe(['footer' => 'Footer Navigation']);
    });

    it('registers multiple menus', function () {
        $this->discovery->restoreFromCache([
            [
                'location' => 'primary',
                'description' => 'Primary Navigation',
                'className' => MenuFixture::class,
            ],
            [
                'location' => 'footer',
                'description' => 'Footer Navigation',
                'className' => MenuFixture::class,
            ],
        ]);

        $this->discovery->apply();

        $calls = wp_stub_get_calls('register_nav_menus');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['locations'])->toBe([
            'primary' => 'Primary Navigation',
            'footer' => 'Footer Navigation',
        ]);
    });

    it('context filter checks has_nav_menu before adding menu', function () {
        $this->discovery->discover(new ReflectionClass(MenuFixture::class));
        $this->discovery->apply();

        // Get the registered filter callback
        $filters = wp_stub_get_calls('add_filter');
        $callback = $filters[0]['args']['callback'];

        // Simulate menu not assigned
        $GLOBALS['wp_stub_nav_menus'] = ['primary' => false];
        $context = $callback([]);

        expect($context['menus'])->toBe([]);

        // Check has_nav_menu was called
        $navMenuCalls = wp_stub_get_calls('has_nav_menu');
        expect($navMenuCalls)->toHaveCount(1);
        expect($navMenuCalls[0]['args']['location'])->toBe('primary');
    });
});
