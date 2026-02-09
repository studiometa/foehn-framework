<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\Concerns\CacheableDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\WpDiscovery;
use Studiometa\Foehn\Discovery\WpDiscoveryItems;

// Test implementation of a cacheable discovery
final class TestCacheableDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function discover(DiscoveryLocation $location, ReflectionClass $class): void
    {
        // For testing, we add items manually
    }

    public function apply(): void
    {
        // No-op for testing
    }

    public function addTestItem(DiscoveryLocation $location, array $item): void
    {
        $this->addItem($location, $item);
    }

    protected function itemToCacheable(array $item): array
    {
        return [
            'name' => $item['name'],
            'value' => $item['value'],
        ];
    }
}

describe('CacheableDiscovery', function () {
    beforeEach(function () {
        $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
        $this->discovery = new TestCacheableDiscovery();
    });

    it('can get cacheable data from discovered items', function () {
        $this->discovery->addTestItem($this->location, [
            'name' => 'test-item',
            'value' => 'test-value',
            'extra' => 'not-cached',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0])->toBe([
            'name' => 'test-item',
            'value' => 'test-value',
        ]);
    });

    it('can restore from cache', function () {
        $cachedData = [
            'App\\' => [
                ['name' => 'cached-item', 'value' => 'cached-value'],
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();

        $items = $this->discovery->getItems()->all();
        expect($items)->toHaveCount(1);
        expect($items[0]['name'])->toBe('cached-item');
    });

    it('returns discovered items when not restored from cache', function () {
        $this->discovery->addTestItem($this->location, [
            'name' => 'discovered-item',
            'value' => 'discovered-value',
        ]);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();

        $items = $this->discovery->getItems()->all();
        expect($items)->toHaveCount(1);
        expect($items[0]['name'])->toBe('discovered-item');
    });

    it('handles multiple items', function () {
        $this->discovery->addTestItem($this->location, ['name' => 'item1', 'value' => 'v1']);
        $this->discovery->addTestItem($this->location, ['name' => 'item2', 'value' => 'v2']);
        $this->discovery->addTestItem($this->location, ['name' => 'item3', 'value' => 'v3']);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(3);
        expect($cacheableData['App\\'][0]['name'])->toBe('item1');
        expect($cacheableData['App\\'][1]['name'])->toBe('item2');
        expect($cacheableData['App\\'][2]['name'])->toBe('item3');
    });

    it('returns empty array when no items', function () {
        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toBeEmpty();
    });
});
