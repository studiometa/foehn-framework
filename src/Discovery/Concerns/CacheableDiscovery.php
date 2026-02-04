<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery\Concerns;

use Tempest\Discovery\DiscoveryItems;

/**
 * Trait for discoveries that support caching.
 *
 * Discoveries that use this trait can export their data in a serializable format
 * and restore from cached data.
 *
 * This trait requires the class to also use the IsDiscovery trait which provides
 * the getItems() method.
 */
trait CacheableDiscovery
{
    /**
     * Cached items restored from cache.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $cachedItems = [];

    /**
     * Whether this discovery was restored from cache.
     */
    protected bool $restoredFromCache = false;

    /**
     * Get cacheable data from the discovery.
     *
     * Each discovery should override this to return serializable data.
     * The data should be structured so it can be used in apply() without
     * requiring reflection.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCacheableData(): array
    {
        $data = [];

        // getItems() is provided by IsDiscovery trait which must be used alongside this trait
        // @mago-expect analyse:non-existent-method
        /** @var DiscoveryItems $items */
        $items = $this->getItems();

        /** @var array<string, mixed> $item */
        foreach ($items as $item) {
            $cacheableItem = $this->itemToCacheable($item);

            if ($cacheableItem !== null) {
                $data[] = $cacheableItem;
            }
        }

        return $data;
    }

    /**
     * Restore discovery from cached data.
     *
     * @param array<int, array<string, mixed>> $data
     */
    public function restoreFromCache(array $data): void
    {
        $this->cachedItems = $data;
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
     * Get all items (from discovery or cache).
     *
     * @return iterable<array<string, mixed>>
     */
    protected function getAllItems(): iterable
    {
        if ($this->restoredFromCache) {
            return $this->cachedItems;
        }

        // getItems() is provided by IsDiscovery trait which must be used alongside this trait
        // @mago-expect analyse:non-existent-method
        /** @var DiscoveryItems $items */
        /** @var iterable<array<string, mixed>> */
        return $this->getItems();
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
