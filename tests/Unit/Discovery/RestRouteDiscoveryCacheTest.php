<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsRestRoute;
use Studiometa\WPTempest\Discovery\RestRouteDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->discovery = new RestRouteDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
});

/**
 * Create a mock method reflector.
 */
function createRestMethodReflector(string $className, string $methodName): object
{
    $classReflector = new class ($className) {
        public function __construct(private string $name) {}

        public function getName(): string
        {
            return $this->name;
        }
    };

    return new class ($classReflector, $methodName) {
        public function __construct(
            private object $class,
            private string $method,
        ) {}

        public function getDeclaringClass(): object
        {
            return $this->class;
        }

        public function getName(): string
        {
            return $this->method;
        }
    };
}

describe('RestRouteDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $attribute = new AsRestRoute(
            namespace: 'my-plugin/v1',
            route: '/posts',
            method: 'GET',
            permission: 'public',
        );

        $methodReflector = createRestMethodReflector('App\\Api\\PostsController', 'index');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'method' => $methodReflector,
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
        $attribute = new AsRestRoute(
            namespace: 'my-plugin/v1',
            route: '/posts',
            method: 'POST',
        );

        $methodReflector = createRestMethodReflector('App\\Api\\PostsController', 'store');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'method' => $methodReflector,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['httpMethod'])->toBe('POST');
    });

    it('handles route with args', function () {
        $attribute = new AsRestRoute(
            namespace: 'my-plugin/v1',
            route: '/posts/(?P<id>\d+)',
            method: 'GET',
            args: [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Post ID',
                ],
            ],
        );

        $methodReflector = createRestMethodReflector('App\\Api\\PostsController', 'show');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'method' => $methodReflector,
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
        $attribute = new AsRestRoute(
            namespace: 'my-plugin/v1',
            route: '/admin/settings',
            method: 'PUT',
            permission: 'canUpdateSettings',
        );

        $methodReflector = createRestMethodReflector('App\\Api\\SettingsController', 'update');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'method' => $methodReflector,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['permission'])->toBe('canUpdateSettings');
    });

    it('handles multiple routes', function () {
        $attribute1 = new AsRestRoute('api/v1', '/users', 'GET', 'public');
        $attribute2 = new AsRestRoute('api/v1', '/users', 'POST');
        $attribute3 = new AsRestRoute('api/v1', '/users/(?P<id>\d+)', 'DELETE');

        $methodReflector1 = createRestMethodReflector('App\\Api\\UsersController', 'index');
        $methodReflector2 = createRestMethodReflector('App\\Api\\UsersController', 'store');
        $methodReflector3 = createRestMethodReflector('App\\Api\\UsersController', 'destroy');

        $this->discovery->getItems()->add($this->location, ['attribute' => $attribute1, 'method' => $methodReflector1]);
        $this->discovery->getItems()->add($this->location, ['attribute' => $attribute2, 'method' => $methodReflector2]);
        $this->discovery->getItems()->add($this->location, ['attribute' => $attribute3, 'method' => $methodReflector3]);

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
        $attribute = new AsRestRoute(
            namespace: 'my-plugin/v1',
            route: '/private',
            method: 'GET',
        );

        $methodReflector = createRestMethodReflector('App\\Api\\PrivateController', 'getData');

        $this->discovery->getItems()->add($this->location, [
            'attribute' => $attribute,
            'method' => $methodReflector,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['permission'])->toBeNull();
    });
});
