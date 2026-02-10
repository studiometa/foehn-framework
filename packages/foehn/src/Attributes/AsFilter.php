<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Mark a method as a WordPress filter hook handler.
 *
 * Usage:
 * ```php
 * #[AsFilter('the_content')]
 * public function filterContent(string $content): string { }
 *
 * #[AsFilter('excerpt_length', priority: 20)]
 * public function excerptLength(): int { }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class AsFilter
{
    /**
     * @param string $hook The WordPress filter hook name
     * @param int $priority Priority for the hook (default: 10)
     * @param int $acceptedArgs Number of arguments the callback accepts (default: 1)
     */
    public function __construct(
        public string $hook,
        public int $priority = 10,
        public int $acceptedArgs = 1,
    ) {}
}
