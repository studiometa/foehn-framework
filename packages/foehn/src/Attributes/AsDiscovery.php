<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;
use Studiometa\Foehn\Discovery\DiscoveryPhase;

/**
 * Declare when a discovery class applies what it found.
 *
 * Discoveries are themselves discovered: any class implementing
 * `Tempest\Discovery\Discovery` in a scanned location is picked up, whether it
 * ships with Foehn, comes from a package or lives in the theme's app directory.
 * This attribute is how such a class chooses its WordPress timing. Without it the
 * class still runs, in the `Main` phase.
 *
 * Usage:
 * ```php
 * #[AsDiscovery(phase: DiscoveryPhase::Main)]
 * final class WidgetDiscovery implements Discovery
 * {
 *     use IsWpDiscovery;
 *
 *     public function discover(DiscoveryLocation $location, ClassReflector $class): void {}
 *
 *     public function apply(): void {}
 * }
 * ```
 *
 * @see \Studiometa\Foehn\Discovery\DiscoveryPhase
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsDiscovery
{
    /**
     * @param DiscoveryPhase $phase The WordPress lifecycle phase apply() runs in
     */
    public function __construct(
        public DiscoveryPhase $phase = DiscoveryPhase::Main,
    ) {}
}
