<?php

declare(strict_types=1);

use Studiometa\WPTempest\Discovery\RestRouteDiscovery;
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
