<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a method as a WordPress shortcode handler.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsShortcode
{
    /**
     * @param string $tag Shortcode tag (e.g., 'gallery', 'button')
     */
    public function __construct(
        public string $tag,
    ) {}
}
