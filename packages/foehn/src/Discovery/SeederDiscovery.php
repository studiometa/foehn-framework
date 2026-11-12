<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Studiometa\Foehn\Attributes\AsSeeder;
use Studiometa\Foehn\Contracts\SeederInterface;
use Studiometa\Foehn\Discovery\Concerns\IsWpDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;

/**
 * Discovers classes marked with #[AsSeeder].
 *
 * A seeder registers nothing with WordPress: `wp foehn seed` reads the items
 * through SeederCatalog and runs them on demand.
 */
final class SeederDiscovery implements Discovery
{
    use IsWpDiscovery;

    /**
     * Discover seeder attributes on classes.
     *
     * @param DiscoveryLocation $location
     * @param ClassReflector $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!$this->isConcrete($class)) {
            return;
        }

        if (!$class->hasAttribute(AsSeeder::class)) {
            return;
        }

        // Checked before the attribute is built, as for CLI commands: a seeder
        // without the interface has no run() to call.
        if (!$class->implements(SeederInterface::class)) {
            return;
        }

        $this->addItem($location, [
            'attribute' => $class->getAttribute(AsSeeder::class),
            'className' => $class->getName(),
        ]);
    }

    /**
     * Nothing to register: seeders run only when `wp foehn seed` asks for them.
     */
    public function apply(): void {}
}
