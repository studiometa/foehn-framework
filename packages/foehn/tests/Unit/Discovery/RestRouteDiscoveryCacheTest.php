<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsRestRoute;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\RestRouteDiscovery;
use Tests\Fixtures\RestRouteFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new RestRouteDiscovery();
});

describe('RestRouteDiscovery caching', function () {
    it('keeps every item under its location namespace', function () {
        discoverFixture($this->discovery, RestRouteFixture::class, $this->location);

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\')->and($cacheData['App\\'])->toHaveCount(3);
    });

    it('restores every item unchanged through a cache file', function () {
        discoverFixture($this->discovery, RestRouteFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new RestRouteDiscovery());

        expect($restored->wasRestoredFromCache())
            ->toBeTrue()
            ->and($restored->getItems()->all())
            ->toEqual($this->discovery->getItems()->all());
    });

    it('restores the attribute as an instance, not an array', function () {
        discoverFixture($this->discovery, RestRouteFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new RestRouteDiscovery())->getItems()->all()[0];

        expect($item['attribute'])
            ->toBeInstanceOf(AsRestRoute::class)
            ->and($item['attribute']->namespace)
            ->toBe('test/v1')
            ->and($item['attribute']->route)
            ->toBe('/items')
            ->and($item['attribute']->getMethodConstant())
            ->toBe('GET')
            ->and($item['className'])
            ->toBe(RestRouteFixture::class)
            ->and($item['methodName'])
            ->toBe('getItems');
    });

    it('keeps each method binding distinct', function () {
        discoverFixture($this->discovery, RestRouteFixture::class, $this->location);

        $items = restoreThroughCacheFile($this->discovery, new RestRouteDiscovery())->getItems()->all();

        expect(array_column($items, 'methodName'))->toBe(['getItems', 'createItem', 'getItem']);
        expect($items[1]['attribute']->getMethodConstant())->toBe('POST');
        expect($items[1]['attribute']->permission)->toBe('public');
        expect($items[2]['attribute']->args)->toBe(['id' => ['type' => 'integer']]);
    });
});
