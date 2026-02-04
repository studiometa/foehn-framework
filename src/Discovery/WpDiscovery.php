<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Discovery;

use ReflectionClass;

/**
 * Interface for wp-tempest discoveries.
 *
 * Unlike Tempest's Discovery interface, this one is NOT auto-discovered
 * by Tempest's DiscoveryDiscovery. The DiscoveryRunner fully owns the
 * lifecycle: scanning classes, calling discover(), and calling apply()
 * at the correct WordPress hook timing.
 */
interface WpDiscovery
{
    /**
     * Inspect a class and collect relevant items for this discovery.
     *
     * @param ReflectionClass<object> $class
     */
    public function discover(ReflectionClass $class): void;

    /**
     * Apply discovered items (register with WordPress).
     */
    public function apply(): void;

    /**
     * Check if any items have been discovered.
     */
    public function hasItems(): bool;
}
