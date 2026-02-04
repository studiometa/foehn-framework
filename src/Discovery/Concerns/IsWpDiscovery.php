<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery\Concerns;

/**
 * Trait providing storage for Foehn discovery items.
 *
 * Replaces Tempest's IsDiscovery trait. Items are stored in a simple array
 * managed entirely by Foehn's DiscoveryRunner.
 *
 * @phpstan-require-implements \Studiometa\Foehn\Discovery\WpDiscovery
 */
trait IsWpDiscovery
{
    /** @var array<int, array<string, mixed>> */
    private array $items = [];

    /**
     * Add a discovered item.
     *
     * @param array<string, mixed> $item
     */
    protected function addItem(array $item): void
    {
        $this->items[] = $item;
    }

    /**
     * Get all discovered items.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Check if any items have been discovered.
     */
    public function hasItems(): bool
    {
        return $this->items !== [];
    }
}
