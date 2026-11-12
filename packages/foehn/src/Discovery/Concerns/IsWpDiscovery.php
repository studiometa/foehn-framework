<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery\Concerns;

use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Item storage for Foehn discoveries.
 *
 * This is Tempest's IsDiscovery with two differences. The collection is created on
 * first use rather than by the caller, so a discovery is usable the moment it is
 * constructed — BootDiscovery seeds it in production, but nothing does when a
 * discovery is resolved from the container on its own. And addItem() gives
 * discover() a name for what it is doing, instead of collection plumbing.
 *
 * @phpstan-require-implements \Tempest\Discovery\Discovery
 */
trait IsWpDiscovery
{
    private DiscoveryItems $discoveryItems;

    public function getItems(): DiscoveryItems
    {
        return $this->discoveryItems ??= new DiscoveryItems();
    }

    public function setItems(DiscoveryItems $items): void
    {
        $this->discoveryItems = $items;
    }

    /**
     * Whether a scanned class is one that could be registered at all.
     *
     * Scanning reaches abstract classes, traits and interfaces — a base block or a
     * shared model carries the same attribute as the class extending it, and
     * registering it would put an uninstantiable class name in front of WordPress.
     *
     * Instantiability is deliberately not the test: `Timber\Post` and `Timber\Term`
     * both declare a protected constructor and are built through their own
     * factories, so every post type and taxonomy model would fail it.
     */
    protected function isConcrete(ClassReflector $class): bool
    {
        $reflection = $class->getReflection();

        return !$reflection->isAbstract() && !$reflection->isTrait() && !$reflection->isEnum();
    }

    /**
     * Record a discovered item against the location its class came from.
     *
     * Whatever goes in here is written to the discovery cache as-is, so it must
     * survive a var_export() round trip: attribute instances and plain values do,
     * closures and resources do not.
     *
     * @param array<string, mixed> $item
     */
    protected function addItem(DiscoveryLocation $location, array $item): void
    {
        $this->getItems()->add($location, $item);
    }
}
