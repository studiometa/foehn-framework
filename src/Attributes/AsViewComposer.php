<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a class as a view composer.
 *
 * View composers automatically add data to matching templates.
 * The class must implement ViewComposerInterface.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsViewComposer
{
    /**
     * @param string|string[] $templates Template patterns to match.
     *                                   Supports wildcards: 'single-*', 'archive-*'
     *                                   Examples: 'single', 'single-post', 'single-*', ['page', 'page-*']
     * @param int $priority Priority for execution (lower = earlier). Default: 10
     */
    public function __construct(
        public string|array $templates,
        public int $priority = 10,
    ) {}

    /**
     * Get templates as array.
     *
     * @return string[]
     */
    public function getTemplates(): array
    {
        return is_array($this->templates) ? $this->templates : [$this->templates];
    }
}
