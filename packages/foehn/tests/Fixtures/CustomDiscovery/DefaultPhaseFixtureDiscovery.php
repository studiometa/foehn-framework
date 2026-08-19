<?php

declare(strict_types=1);

namespace Tests\Fixtures\CustomDiscovery;

use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * A discovery carrying no #[AsDiscovery] at all, which is the common case: most
 * things register on `init`, so the attribute is only needed to ask for another
 * phase.
 */
final class DefaultPhaseFixtureDiscovery implements Discovery
{
    use IsWpDiscovery;

    public static int $applied = 0;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void {}

    public function apply(): void
    {
        self::$applied++;
    }
}
