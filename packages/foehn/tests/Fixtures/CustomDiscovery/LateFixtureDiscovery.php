<?php

declare(strict_types=1);

namespace Tests\Fixtures\CustomDiscovery;

use Studiometa\Foehn\Attributes\AsDiscovery;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * A discovery that ships with neither the framework nor a package.
 *
 * It exists to prove the thing #[AsDiscovery] is for: a class in the app directory
 * implementing Discovery is found, resolved and applied at the phase it asks for.
 */
#[AsDiscovery(phase: DiscoveryPhase::Late)]
final class LateFixtureDiscovery implements Discovery
{
    use IsWpDiscovery;

    public static int $applied = 0;

    public function discover(DiscoveryLocation $location, ClassReflector $class): void {}

    public function apply(): void
    {
        self::$applied++;
    }
}
