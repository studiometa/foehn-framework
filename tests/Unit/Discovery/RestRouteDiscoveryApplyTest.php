<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\RestRouteDiscovery;
use Tests\Fixtures\RestRouteFixture;

beforeEach(function () {
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new RestRouteDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('RestRouteDiscovery apply', function () {
    it('registers rest_api_init action', function () {
        $this->discovery->discover(new ReflectionClass(RestRouteFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');

        expect($actions)->toHaveCount(1);
        expect($actions[0]['args']['hook'])->toBe('rest_api_init');
    });

    it('registers routes when rest_api_init callback is invoked', function () {
        $this->discovery->discover(new ReflectionClass(RestRouteFixture::class));
        $this->discovery->apply();

        // Simulate WordPress calling the rest_api_init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $routes = wp_stub_get_calls('register_rest_route');

        expect($routes)->toHaveCount(3);

        // GET /items
        expect($routes[0]['args']['namespace'])->toBe('test/v1');
        expect($routes[0]['args']['route'])->toBe('/items');
        expect($routes[0]['args']['args']['methods'])->toBe('GET');

        // POST /items
        expect($routes[1]['args']['args']['methods'])->toBe('POST');

        // GET /items/{id}
        expect($routes[2]['args']['route'])->toBe('/items/(?P<id>\d+)');
    });

    it('registers no routes when no items discovered', function () {
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');
        expect($actions)->toHaveCount(1);

        $callback = $actions[0]['args']['callback'];
        $callback();

        expect(wp_stub_get_calls('register_rest_route'))->toBeEmpty();
    });
});

describe('RestRouteDiscovery default permission', function () {
    it('uses current_user_can with edit_posts by default', function () {
        $discovery = new RestRouteDiscovery();
        $discovery->discover(new ReflectionClass(RestRouteFixture::class));
        $discovery->apply();

        // Trigger rest_api_init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $routes = wp_stub_get_calls('register_rest_route');

        // First route (getItems) has no permission set, should use default
        $permissionCallback = $routes[0]['args']['args']['permission_callback'];

        // Call the permission callback - it will call current_user_can('edit_posts')
        $permissionCallback();

        $canCalls = wp_stub_get_calls('current_user_can');
        expect($canCalls)->not->toBeEmpty();
        expect($canCalls[0]['args']['capability'])->toBe('edit_posts');
    });

    it('uses custom capability from config', function () {
        $config = new FoehnConfig(restDefaultCapability: 'manage_options');
        $discovery = new RestRouteDiscovery($config);
        $discovery->discover(new ReflectionClass(RestRouteFixture::class));
        $discovery->apply();

        // Trigger rest_api_init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $routes = wp_stub_get_calls('register_rest_route');
        $permissionCallback = $routes[0]['args']['args']['permission_callback'];

        $permissionCallback();

        $canCalls = wp_stub_get_calls('current_user_can');
        expect($canCalls)->not->toBeEmpty();
        expect($canCalls[0]['args']['capability'])->toBe('manage_options');
    });

    it('falls back to is_user_logged_in when capability is null', function () {
        $config = new FoehnConfig(restDefaultCapability: null);
        $discovery = new RestRouteDiscovery($config);
        $discovery->discover(new ReflectionClass(RestRouteFixture::class));
        $discovery->apply();

        // Trigger rest_api_init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $routes = wp_stub_get_calls('register_rest_route');
        $permissionCallback = $routes[0]['args']['args']['permission_callback'];

        // Set logged in state and call permission callback
        $GLOBALS['wp_stub_logged_in'] = true;
        expect($permissionCallback())->toBeTrue();

        $GLOBALS['wp_stub_logged_in'] = false;
        expect($permissionCallback())->toBeFalse();
    });

    it('allows public access when permission is public', function () {
        $discovery = new RestRouteDiscovery();
        $discovery->discover(new ReflectionClass(RestRouteFixture::class));
        $discovery->apply();

        // Trigger rest_api_init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $routes = wp_stub_get_calls('register_rest_route');

        // Second route (createItem) has permission: 'public'
        $permissionCallback = $routes[1]['args']['args']['permission_callback'];
        expect($permissionCallback())->toBeTrue();
    });
});
