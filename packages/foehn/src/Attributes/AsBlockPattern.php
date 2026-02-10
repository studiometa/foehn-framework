<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a class as a Block Pattern.
 *
 * The class may implement BlockPatternInterface for dynamic content.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlockPattern
{
    /**
     * @param string $name Pattern name (e.g., 'theme/hero-with-cta')
     * @param string $title Pattern title displayed in editor
     * @param string[] $categories Pattern categories
     * @param string[] $keywords Search keywords
     * @param string[] $blockTypes Block types this pattern is for
     * @param string|null $description Pattern description
     * @param string|null $template Template path (auto-resolved if null)
     * @param int $viewportWidth Viewport width for preview
     * @param bool $inserter Whether to show in inserter
     */
    public function __construct(
        public string $name,
        public string $title,
        public array $categories = [],
        public array $keywords = [],
        public array $blockTypes = [],
        public ?string $description = null,
        public ?string $template = null,
        public int $viewportWidth = 1200,
        public bool $inserter = true,
    ) {}

    /**
     * Get the resolved template path.
     */
    public function getTemplatePath(): string
    {
        if ($this->template !== null) {
            return $this->template;
        }

        // Auto-resolve: 'theme/hero-with-cta' → 'patterns/hero-with-cta'
        $slug = preg_replace('/^[^\/]+\//', '', $this->name) ?? $this->name;

        return 'patterns/' . $slug;
    }
}
