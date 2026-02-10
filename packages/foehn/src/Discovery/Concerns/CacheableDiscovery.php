<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery\Concerns;

use Studiometa\Foehn\Discovery\WpDiscoveryItems;

/**
 * Trait for discoveries that support caching.
 *
 * Discoveries that use this trait can export their data in a serializable format
 * and restore from cached data via WpDiscoveryItems.
 *
 * This trait requires the class to also use the IsWpDiscovery trait which provides
 * getItems()/setItems() methods.
 */
trait CacheableDiscovery
{
    /**
     * Whether this discovery was restored from cache.
     */
    protected bool $restoredFromCache = false;

    /**
     * Get cacheable data from the discovery.
     *
     * Converts all discovered items into a serializable format grouped by location.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function getCacheableData(): array
    {
        /** @var WpDiscoveryItems $items */
        $items = $this->getItems();
        /** @var array<string, list<array<string, mixed>>> $data */
        $data = [];

        /** @var string $namespace */
        foreach ($items->toArray() as $namespace => $locationItems) {
            /** @var array<string, mixed> $item */
            foreach ($locationItems as $item) {
                $cacheableItem = $this->itemToCacheable($item);

                if ($cacheableItem !== null) {
                    $data[$namespace][] = $cacheableItem;
                }
            }
        }

        return $data;
    }

    /**
     * Restore discovery from cached data.
     *
     * @param array<string, list<array<string, mixed>>> $data
     */
    public function restoreFromCache(array $data): void
    {
        $this->setItems(WpDiscoveryItems::fromArray($data));
        $this->restoredFromCache = true;
    }

    /**
     * Check if this discovery was restored from cache.
     */
    public function wasRestoredFromCache(): bool
    {
        return $this->restoredFromCache;
    }

    /**
     * Convert a discovered item to a cacheable format.
     *
     * Override this method in each discovery to define how items are serialized.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    abstract protected function itemToCacheable(array $item): ?array;
}
