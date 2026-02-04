<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery\Concerns;

/**
 * Trait for discoveries that support caching.
 *
 * Discoveries that use this trait can export their data in a serializable format
 * and restore from cached data.
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

        foreach ($this->discoveryItems as $item) {
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

        return $this->discoveryItems;
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
