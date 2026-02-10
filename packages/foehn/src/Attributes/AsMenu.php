<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Attribute to register a WordPress navigation menu location.
 *
 * Usage:
 * ```php
 * #[AsMenu(location: 'primary', description: 'Primary Navigation')]
 * final class PrimaryMenu {}
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMenu
{
    /**
     * @param string $location    Menu location slug (used as key in register_nav_menus)
     * @param string $description Human-readable description shown in admin
     */
    public function __construct(
        public string $location,
        public string $description,
    ) {}
}
