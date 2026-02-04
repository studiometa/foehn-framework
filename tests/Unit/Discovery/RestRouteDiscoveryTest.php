<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\RestRouteDiscovery;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\RestRouteFixture;

beforeEach(function () {
    $this->discovery = new RestRouteDiscovery();
});

describe('RestRouteDiscovery', function () {
    it('discovers REST route attributes on methods', function () {
        $this->discovery->discover(new ReflectionClass(RestRouteFixture::class));

        $items = $this->discovery->getItems();

        expect($items)->toHaveCount(3);

        // GET /items (default method)
        expect($items[0]['namespace'])->toBe('test/v1');
        expect($items[0]['route'])->toBe('/items');
        expect($items[0]['httpMethod'])->toBe('GET');
        expect($items[0]['className'])->toBe(RestRouteFixture::class);
        expect($items[0]['methodName'])->toBe('getItems');
        expect($items[0]['permission'])->toBeNull();
        expect($items[0]['args'])->toBe([]);

        // POST /items with public permission
        expect($items[1]['httpMethod'])->toBe('POST');
        expect($items[1]['permission'])->toBe('public');

        // GET /items/{id} with args
        expect($items[2]['route'])->toBe('/items/(?P<id>\d+)');
        expect($items[2]['args'])->toBe(['id' => ['type' => 'integer']]);
    });

    it('ignores classes without REST route attributes', function () {
        $this->discovery->discover(new ReflectionClass(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toBeEmpty();
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->hasItems())->toBeFalse();

        $this->discovery->discover(new ReflectionClass(RestRouteFixture::class));

        expect($this->discovery->hasItems())->toBeTrue();
    });
});
