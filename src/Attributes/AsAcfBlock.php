<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Attributes;

use Attribute;

/**
 * Register a class as an ACF Block.
 *
 * The class must implement AcfBlockInterface.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsAcfBlock
{
    /**
     * @param string $name Block name (without namespace, e.g., 'hero')
     * @param string $title Block title displayed in editor
     * @param string $category Block category (common, formatting, layout, widgets, embed, or custom)
     * @param string|null $icon Dashicon name or custom SVG
     * @param string|null $description Block description
     * @param string[] $keywords Search keywords
     * @param string $mode Display mode: 'preview', 'edit', or 'auto'
     * @param array<string, mixed> $supports Block supports configuration
     * @param string|null $template Template path (auto-resolved if null)
     * @param string[] $postTypes Allowed post types (empty = all)
     * @param string|null $parent Parent block name (for inner blocks)
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $category = 'common',
        public ?string $icon = null,
        public ?string $description = null,
        public array $keywords = [],
        public string $mode = 'preview',
        public array $supports = [],
        public ?string $template = null,
        public array $postTypes = [],
        public ?string $parent = null,
    ) {}

    /**
     * Get the full block name with acf/ prefix.
     */
    public function getFullName(): string
    {
        return 'acf/' . $this->name;
    }
}
