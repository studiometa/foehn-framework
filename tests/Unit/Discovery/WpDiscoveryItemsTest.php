<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\WpDiscoveryItems;

describe('WpDiscoveryItems', function () {
    it('starts empty', function () {
        $items = new WpDiscoveryItems();

        expect($items->isEmpty())->toBeTrue();
        expect($items->count())->toBe(0);
        expect($items->all())->toBe([]);
        expect($items->isLoaded())->toBeFalse();
    });

    it('adds items with location tracking', function () {
        $items = new WpDiscoveryItems();
        $location = DiscoveryLocation::app('App\\', '/app');

        $items->add($location, ['name' => 'test']);

        expect($items->isEmpty())->toBeFalse();
        expect($items->count())->toBe(1);
        expect($items->isLoaded())->toBeTrue();
    });

    it('returns items for a specific location', function () {
        $items = new WpDiscoveryItems();
        $appLocation = DiscoveryLocation::app('App\\', '/app');
        $vendorLocation = DiscoveryLocation::vendor('Vendor\\', '/vendor');

        $items->add($appLocation, ['name' => 'app-item']);
        $items->add($vendorLocation, ['name' => 'vendor-item']);

        $appItems = $items->getForLocation($appLocation);
        expect($appItems)->toHaveCount(1);
        expect($appItems[0]['name'])->toBe('app-item');

        $vendorItems = $items->getForLocation($vendorLocation);
        expect($vendorItems)->toHaveCount(1);
        expect($vendorItems[0]['name'])->toBe('vendor-item');
    });

    it('checks if a location has items', function () {
        $items = new WpDiscoveryItems();
        $location = DiscoveryLocation::app('App\\', '/app');
        $other = DiscoveryLocation::vendor('Other\\', '/other');

        expect($items->hasLocation($location))->toBeFalse();

        $items->add($location, ['name' => 'test']);

        expect($items->hasLocation($location))->toBeTrue();
        expect($items->hasLocation($other))->toBeFalse();
    });

    it('returns all items as flat array', function () {
        $items = new WpDiscoveryItems();
        $loc1 = DiscoveryLocation::app('App\\', '/app');
        $loc2 = DiscoveryLocation::vendor('Vendor\\', '/vendor');

        $items->add($loc1, ['name' => 'a']);
        $items->add($loc1, ['name' => 'b']);
        $items->add($loc2, ['name' => 'c']);

        $all = $items->all();
        expect($all)->toHaveCount(3);
        expect($all[0]['name'])->toBe('a');
        expect($all[1]['name'])->toBe('b');
        expect($all[2]['name'])->toBe('c');
    });

    it('is iterable', function () {
        $items = new WpDiscoveryItems();
        $location = DiscoveryLocation::app('App\\', '/app');

        $items->add($location, ['name' => 'x']);
        $items->add($location, ['name' => 'y']);

        $collected = [];
        foreach ($items as $item) {
            $collected[] = $item['name'];
        }

        expect($collected)->toBe(['x', 'y']);
    });

    it('is countable', function () {
        $items = new WpDiscoveryItems();
        $location = DiscoveryLocation::app('App\\', '/app');

        expect(count($items))->toBe(0);

        $items->add($location, ['a' => 1]);
        $items->add($location, ['b' => 2]);

        expect(count($items))->toBe(2);
    });

    it('serializes to array', function () {
        $items = new WpDiscoveryItems();
        $location = DiscoveryLocation::app('App\\', '/app');

        $items->add($location, ['name' => 'test']);

        $array = $items->toArray();

        expect($array)->toBe([
            'App\\' => [
                ['name' => 'test'],
            ],
        ]);
    });

    it('restores from array', function () {
        $data = [
            'App\\' => [
                ['name' => 'cached-1'],
                ['name' => 'cached-2'],
            ],
            'Vendor\\' => [
                ['name' => 'vendor-1'],
            ],
        ];

        $items = WpDiscoveryItems::fromArray($data);

        expect($items->isLoaded())->toBeTrue();
        expect($items->count())->toBe(3);
        expect($items->all())->toHaveCount(3);
        expect($items->toArray())->toBe($data);
    });

    it('marks as loaded', function () {
        $items = new WpDiscoveryItems();

        expect($items->isLoaded())->toBeFalse();

        $items->markLoaded();

        expect($items->isLoaded())->toBeTrue();
    });

    it('returns empty array for unknown location', function () {
        $items = new WpDiscoveryItems();
        $unknown = DiscoveryLocation::app('Unknown\\', '/unknown');

        expect($items->getForLocation($unknown))->toBe([]);
    });
});
