<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\RestRouteDiscovery;
use Tests\Fixtures\NoAttributeFixture;
use Tests\Fixtures\RestRouteFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    $this->discovery = new RestRouteDiscovery();
});

describe('RestRouteDiscovery', function () {
    it('discovers REST route attributes on methods', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(RestRouteFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(3);

        // GET /items (default method)
        expect($items[0]['attribute']->namespace)->toBe('test/v1');
        expect($items[0]['attribute']->route)->toBe('/items');
        expect($items[0]['attribute']->getMethodConstant())->toBe('GET');
        expect($items[0]['className'])->toBe(RestRouteFixture::class);
        expect($items[0]['methodName'])->toBe('getItems');
        expect($items[0]['attribute']->permission)->toBeNull();
        expect($items[0]['attribute']->args)->toBe([]);

        // POST /items with public permission
        expect($items[1]['attribute']->getMethodConstant())->toBe('POST');
        expect($items[1]['attribute']->permission)->toBe('public');

        // GET /items/{id} with args
        expect($items[2]['attribute']->route)->toBe('/items/(?P<id>\d+)');
        expect($items[2]['attribute']->args)->toBe(['id' => ['type' => 'integer']]);
    });

    it('ignores classes without REST route attributes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(RestRouteFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });
});
