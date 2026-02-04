<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a custom block category.
 *
 * Can be used on any class to register block categories.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class AsBlockCategory
{
    /**
     * @param string $slug Category slug
     * @param string $title Category title
     * @param string|null $icon Dashicon name
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $icon = null,
    ) {}
}
