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
}
