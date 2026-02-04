<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Attributes;

use Attribute;

/**
 * Register a class as a native Gutenberg block.
 *
 * The class must implement BlockInterface or InteractiveBlockInterface.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsBlock
{
    /**
     * @param string $name Block name (with namespace, e.g., 'theme/counter')
     * @param string $title Block title displayed in editor
     * @param string $category Block category
     * @param string|null $icon Dashicon name or custom SVG
     * @param string|null $description Block description
     * @param string[] $keywords Search keywords
     * @param array<string, mixed> $supports Block supports configuration
     * @param string|null $parent Parent block name (for inner blocks)
     * @param string[] $ancestor Ancestor block names
     * @param bool $interactivity Enable WordPress Interactivity API
     * @param string|null $interactivityNamespace Custom namespace for interactivity (defaults to block name)
     * @param string|null $template Template path (auto-resolved if null)
     * @param string|null $editorScript Editor script handle or path
     * @param string|null $editorStyle Editor style handle or path
     * @param string|null $style Frontend style handle or path
     * @param string|null $viewScript Frontend script for interactivity (view.js)
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $category = 'widgets',
        public ?string $icon = null,
        public ?string $description = null,
        public array $keywords = [],
        public array $supports = [],
        public ?string $parent = null,
        public array $ancestor = [],
        public bool $interactivity = false,
        public ?string $interactivityNamespace = null,
        public ?string $template = null,
        public ?string $editorScript = null,
        public ?string $editorStyle = null,
        public ?string $style = null,
        public ?string $viewScript = null,
    ) {}

    /**
     * Get the interactivity namespace.
     */
    public function getInteractivityNamespace(): string
    {
        return $this->interactivityNamespace ?? $this->name;
    }
}
