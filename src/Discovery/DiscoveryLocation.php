<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

/**
 * Represents a location where classes are discovered.
 *
 * Inspired by Tempest's DiscoveryLocation, this value object distinguishes
 * between vendor (package) classes and application classes, enabling smarter
 * cache invalidation and filtering.
 */
final readonly class DiscoveryLocation
{
    public function __construct(
        /** The namespace prefix for this location (e.g. 'App\\') */
        public string $namespace,

        /** The filesystem path for this location */
        public string $path,

        /** Whether this location is a vendor package */
        public bool $isVendor = false,
    ) {}

    /**
     * Create a location for vendor packages.
     */
    public static function vendor(string $namespace, string $path): self
    {
        return new self($namespace, $path, isVendor: true);
    }

    /**
     * Create a location for application code.
     */
    public static function app(string $namespace, string $path): self
    {
        return new self($namespace, $path, isVendor: false);
    }
}
