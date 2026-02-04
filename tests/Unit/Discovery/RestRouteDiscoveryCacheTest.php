<?php

declare(strict_types=1);

use Studiometa\WPTempest\Discovery\RestRouteDiscovery;

beforeEach(function () {
    $this->discovery = new RestRouteDiscovery();
});

describe('RestRouteDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'namespace' => 'my-plugin/v1',
            'route' => '/posts',
            'httpMethod' => 'GET',
            'className' => 'App\\Api\\PostsController',
            'methodName' => 'index',
            'permission' => 'public',
            'args' => [],
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'namespace' => 'my-plugin/v1',
            'route' => '/posts',
            'httpMethod' => 'GET',
            'className' => 'App\\Api\\PostsController',
            'methodName' => 'index',
            'permission' => 'public',
            'args' => [],
        ]);
    });

    it('handles POST method', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'namespace' => 'my-plugin/v1',
            'route' => '/posts',
            'httpMethod' => 'POST',
            'className' => 'App\\Api\\PostsController',
            'methodName' => 'store',
            'permission' => null,
            'args' => [],
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['httpMethod'])->toBe('POST');
    });

    it('handles route with args', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'namespace' => 'my-plugin/v1',
            'route' => '/posts/(?P<id>\d+)',
            'httpMethod' => 'GET',
            'className' => 'App\\Api\\PostsController',
            'methodName' => 'show',
            'permission' => null,
            'args' => [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Post ID',
                ],
            ],
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['args'])->toBe([
            'id' => [
                'required' => true,
                'type' => 'integer',
                'description' => 'Post ID',
            ],
        ]);
    });

    it('handles custom permission callback', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'namespace' => 'my-plugin/v1',
            'route' => '/admin/settings',
            'httpMethod' => 'PUT',
            'className' => 'App\\Api\\SettingsController',
            'methodName' => 'update',
            'permission' => 'canUpdateSettings',
            'args' => [],
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['permission'])->toBe('canUpdateSettings');
    });

    it('handles multiple routes', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');

        $ref->invoke($this->discovery, [
            'namespace' => 'api/v1', 'route' => '/users', 'httpMethod' => 'GET',
            'className' => 'App\\Api\\UsersController', 'methodName' => 'index',
            'permission' => 'public', 'args' => [],
        ]);
        $ref->invoke($this->discovery, [
            'namespace' => 'api/v1', 'route' => '/users', 'httpMethod' => 'POST',
            'className' => 'App\\Api\\UsersController', 'methodName' => 'store',
            'permission' => null, 'args' => [],
        ]);
        $ref->invoke($this->discovery, [
            'namespace' => 'api/v1', 'route' => '/users/(?P<id>\d+)', 'httpMethod' => 'DELETE',
            'className' => 'App\\Api\\UsersController', 'methodName' => 'destroy',
            'permission' => null, 'args' => [],
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(3);
        expect($cacheableData[0]['httpMethod'])->toBe('GET');
        expect($cacheableData[1]['httpMethod'])->toBe('POST');
        expect($cacheableData[2]['httpMethod'])->toBe('DELETE');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'namespace' => 'cached/v1',
                'route' => '/items',
                'httpMethod' => 'GET',
                'className' => 'App\\Api\\ItemsController',
                'methodName' => 'list',
                'permission' => 'public',
                'args' => [],
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles null permission (requires auth)', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, [
            'namespace' => 'my-plugin/v1',
            'route' => '/private',
            'httpMethod' => 'GET',
            'className' => 'App\\Api\\PrivateController',
            'methodName' => 'getData',
            'permission' => null,
            'args' => [],
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['permission'])->toBeNull();
    });
});
