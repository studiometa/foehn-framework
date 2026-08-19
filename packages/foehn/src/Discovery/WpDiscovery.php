<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;

/**
 * Interface for Foehn discoveries.
 *
 * Inspired by Tempest's Discovery interface but adapted for WordPress lifecycle.
 * The DiscoveryRunner fully owns the lifecycle: scanning classes, calling discover(),
 * and calling apply() at the correct WordPress hook timing.
 *
 * Key differences from Tempest:
 * - discover() receives a DiscoveryLocation for origin tracking
 * - Items are managed via WpDiscoveryItems for location-based storage
 * - apply() is called at specific WordPress lifecycle phases (early/main/late)
 *
 * The cache round trip is part of the interface rather than an optional trait:
 * every discovery is cacheable, so callers do not have to probe for the methods.
 * Implementations get them from the CacheableDiscovery trait.
 */
interface WpDiscovery
{
    /**
     * Inspect a class and collect relevant items for this discovery.
     *
     * @param DiscoveryLocation $location The location where the class was found
     * @param ReflectionClass<object> $class
     */
    public function discover(DiscoveryLocation $location, ReflectionClass $class): void;

    /**
     * Get the discovery items collection.
     */
    public function getItems(): WpDiscoveryItems;

    /**
     * Set the discovery items collection (used for cache restoration).
     */
    public function setItems(WpDiscoveryItems $items): void;

    /**
     * Apply discovered items (register with WordPress).
     */
    public function apply(): void;

    /**
     * Check if any items have been discovered.
     */
    public function hasItems(): bool;

    /**
     * Export the discovered items in a form the discovery cache can write.
     *
     * @return array<string, list<array<string, mixed>>> Items grouped by location namespace
     */
    public function getCacheableData(): array;

    /**
     * Restore the discovered items from cached data.
     *
     * @param array<string, list<array<string, mixed>>> $data
     */
    public function restoreFromCache(array $data): void;

    /**
     * Check whether the items came from the cache rather than from a scan.
     */
    public function wasRestoredFromCache(): bool;
}
