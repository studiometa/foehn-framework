<?php

declare(strict_types=1);

namespace Tests\Fixtures\ScalarItems;

use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * A discovery whose items are not the array shape Foehn's own use.
 *
 * Nothing requires that shape — DiscoveryItems takes any value — so a listing
 * that assumed it would fatal on somebody else's discovery.
 */
final class ScalarItemDiscovery implements Discovery
{
    use IsWpDiscovery;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        // Not addItem(), which takes the array shape Foehn's own discoveries use.
        $this->getItems()->add($location, $class->getName());
    }

    public function apply(): void {}
}
