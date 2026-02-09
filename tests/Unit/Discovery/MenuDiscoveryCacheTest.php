<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\MenuDiscovery;
use Tests\Fixtures\MenuFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new MenuDiscovery();
});

describe('MenuDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $this->discovery->discover($this->location, new ReflectionClass(MenuFixture::class));

        $cacheData = $this->discovery->getCacheableData();

        expect($cacheData)->toHaveKey('App\\');
        expect($cacheData['App\\'])->toHaveCount(1);
        expect($cacheData['App\\'][0]['location'])->toBe('primary');
        expect($cacheData['App\\'][0]['description'])->toBe('Primary Navigation');
        expect($cacheData['App\\'][0]['className'])->toBe(MenuFixture::class);
    });

    it('handles multiple menus', function () {
        // Manually add items to simulate multiple discovered menus
        $this->discovery->restoreFromCache(['App\\' => [
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
        ]]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('can restore from cache', function () {
        $cacheData = [
            [
                'location' => 'sidebar',
                'description' => 'Sidebar Menu',
                'className' => MenuFixture::class,
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cacheData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles minimal configuration', function () {
        $this->discovery->discover($this->location, new ReflectionClass(MenuFixture::class));

        $cacheData = $this->discovery->getCacheableData();

        // All required fields should be present
        expect($cacheData['App\\'][0])->toHaveKeys(['location', 'description', 'className']);
    });
});
