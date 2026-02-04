<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Attributes;

use Attribute;

/**
 * Register a class as a WP-CLI command.
 *
 * Commands are registered under the 'tempest' namespace:
 * `wp tempest <name>`
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCliCommand
{
    /**
     * @param string $name Command name (e.g., 'make:post-type')
     * @param string $description Short description for help
     * @param string|null $longDescription Long description with examples (docblock format)
     */
    public function __construct(
        public string $name,
        public string $description,
        public ?string $longDescription = null,
    ) {}
}
