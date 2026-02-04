<?php

declare(strict_types=1);

use Studiometa\WPTempest\Discovery\Concerns\CacheableDiscovery;
use Studiometa\WPTempest\Discovery\Concerns\IsWpDiscovery;
use Studiometa\WPTempest\Discovery\WpDiscovery;

// Test implementation of a cacheable discovery
final class TestCacheableDiscovery implements WpDiscovery
{
    use IsWpDiscovery;
    use CacheableDiscovery;

    public function discover(ReflectionClass $class): void
    {
        // For testing, we add items manually
    }

    public function apply(): void
    {
        // No-op for testing
    }

    public function addTestItem(array $item): void
    {
        $this->addItem($item);
    }

    protected function itemToCacheable(array $item): array
    {
        return [
            'name' => $item['name'],
            'value' => $item['value'],
        ];
    }

    public function getItemsForTest(): iterable
    {
        return $this->getAllItems();
    }
}

describe('CacheableDiscovery', function () {
    beforeEach(function () {
        $this->discovery = new TestCacheableDiscovery();
    });

    it('can get cacheable data from discovered items', function () {
        $this->discovery->addTestItem([
            'name' => 'test-item',
            'value' => 'test-value',
            'extra' => 'not-cached',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'name' => 'test-item',
            'value' => 'test-value',
        ]);
    });

    it('can restore from cache', function () {
        $cachedData = [
            ['name' => 'cached-item', 'value' => 'cached-value'],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();

        $items = iterator_to_array($this->discovery->getItemsForTest());
        expect($items)->toHaveCount(1);
        expect($items[0]['name'])->toBe('cached-item');
    });

    it('returns discovered items when not restored from cache', function () {
        $this->discovery->addTestItem([
            'name' => 'discovered-item',
            'value' => 'discovered-value',
        ]);

        expect($this->discovery->wasRestoredFromCache())->toBeFalse();

        $items = iterator_to_array($this->discovery->getItemsForTest());
        expect($items)->toHaveCount(1);
        expect($items[0]['name'])->toBe('discovered-item');
    });

    it('handles multiple items', function () {
        $this->discovery->addTestItem(['name' => 'item1', 'value' => 'v1']);
        $this->discovery->addTestItem(['name' => 'item2', 'value' => 'v2']);
        $this->discovery->addTestItem(['name' => 'item3', 'value' => 'v3']);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(3);
        expect($cacheableData[0]['name'])->toBe('item1');
        expect($cacheableData[1]['name'])->toBe('item2');
        expect($cacheableData[2]['name'])->toBe('item3');
    });

    it('returns empty array when no items', function () {
        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toBeEmpty();
    });
});
