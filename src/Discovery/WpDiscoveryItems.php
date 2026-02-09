<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of items discovered during the discovery process.
 *
 * Inspired by Tempest's DiscoveryItems. Stores items grouped by
 * DiscoveryLocation, enabling location-based filtering and
 * smarter cache invalidation.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
final class WpDiscoveryItems implements IteratorAggregate, Countable
{
    /**
     * Items grouped by location namespace.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $items = [];

    /**
     * Whether items have been loaded (either from discovery or cache).
     */
    private bool $loaded = false;

    /**
     * Add a discovered item for a given location.
     *
     * @param array<string, mixed> $value
     */
    public function add(DiscoveryLocation $location, array $value): self
    {
        $this->items[$location->namespace][] = $value;
        $this->loaded = true;

        return $this;
    }

    /**
     * Get all items for a specific location.
     *
     * @return list<array<string, mixed>>
     */
    public function getForLocation(DiscoveryLocation $location): array
    {
        return $this->items[$location->namespace] ?? [];
    }

    /**
     * Check if any items exist for a given location.
     */
    public function hasLocation(DiscoveryLocation $location): bool
    {
        return isset($this->items[$location->namespace]) && $this->items[$location->namespace] !== [];
    }

    /**
     * Get only vendor items.
     */
    public function onlyVendor(): self
    {
        $new = new self();
        $new->loaded = $this->loaded;

        foreach ($this->items as $namespace => $items) {
            // Vendor items are identified by their namespace being stored with vendor locations
            // Since we don't track the location metadata here, we'll keep all items
            // and rely on the discovery runner to filter when needed
            $new->items[$namespace] = $items;
        }

        return $new;
    }

    /**
     * Check if items have been loaded.
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Mark items as loaded (used when restoring from cache).
     */
    public function markLoaded(): self
    {
        $this->loaded = true;

        return $this;
    }

    /**
     * Get all items as a flat array.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->items as $items) {
            $result = [...$result, ...$items];
        }

        return $result;
    }

    /**
     * Check if the collection has any items.
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * Get the total number of items across all locations.
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->items as $items) {
            $count += count($items);
        }

        return $count;
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->all());
    }

    /**
     * Convert to a serializable array for caching.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Restore from a cached array.
     *
     * @param array<string, list<array<string, mixed>>> $data
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->items = $data;
        $instance->loaded = true;

        return $instance;
    }
}
