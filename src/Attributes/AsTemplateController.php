<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Attributes;

use Attribute;

/**
 * Register a class as a template controller.
 *
 * Template controllers handle the full rendering of WordPress templates.
 * The class must implement TemplateControllerInterface.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsTemplateController
{
    /**
     * @param string|string[] $templates Template patterns to match.
     *                                   Uses WordPress template hierarchy names.
     *                                   Supports wildcards: 'single-*', 'archive-*'
     *                                   Examples: 'single', 'single-post', 'page-contact', ['home', 'front-page']
     * @param int $priority Priority for template_include filter (lower = earlier). Default: 10
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
