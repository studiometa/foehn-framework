<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery\Concerns;

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\WpDiscoveryItems;

/**
 * Trait providing WpDiscoveryItems storage for Foehn discoveries.
 *
 * Replaces the old simple array storage with location-aware WpDiscoveryItems.
 * Each discovery class uses this trait and adds items with their location context.
 *
 * @phpstan-require-implements \Studiometa\Foehn\Discovery\WpDiscovery
 */
trait IsWpDiscovery
{
    private ?WpDiscoveryItems $discoveryItems = null;

    /**
     * Add a discovered item for the given location.
     *
     * @param DiscoveryLocation $location
     * @param array<string, mixed> $item
     */
    protected function addItem(DiscoveryLocation $location, array $item): void
    {
        $this->getItems()->add($location, $item);
    }

    /**
     * Get the discovery items collection.
     */
    public function getItems(): WpDiscoveryItems
    {
        if ($this->discoveryItems === null) {
            $this->discoveryItems = new WpDiscoveryItems();
        }

        return $this->discoveryItems;
    }

    /**
     * Set the discovery items collection (used for cache restoration).
     */
    public function setItems(WpDiscoveryItems $items): void
    {
        $this->discoveryItems = $items;
    }

    /**
     * Check if any items have been discovered.
     */
    public function hasItems(): bool
    {
        return !$this->getItems()->isEmpty();
    }
}
