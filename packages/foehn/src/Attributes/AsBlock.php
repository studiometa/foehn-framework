<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

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
     * @param list<string> $allowedBlocks Block names allowed inside this block
     * @param list<mixed> $innerBlocksTemplate InnerBlocks template array
     * @param string|bool|null $innerBlocksTemplateLock InnerBlocks template lock ('all', 'insert', 'contentOnly' or false)
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
        public array $allowedBlocks = [],
        public array $innerBlocksTemplate = [],
        public string|bool|null $innerBlocksTemplateLock = null,
    ) {}

    /**
     * Get the interactivity namespace.
     */
    public function getInteractivityNamespace(): string
    {
        return $this->interactivityNamespace ?? $this->name;
    }

    /**
     * The single definition of what makes a block a container.
     *
     * A block accepts inner blocks as soon as one of the three innerBlocks
     * parameters is set. Static because blocks restored from the discovery cache
     * have those values without an attribute instance.
     *
     * @param list<string> $allowedBlocks
     * @param list<mixed> $innerBlocksTemplate
     */
    public static function hasInnerBlocks(
        array $allowedBlocks,
        array $innerBlocksTemplate,
        string|bool|null $innerBlocksTemplateLock,
    ): bool {
        return $allowedBlocks !== [] || $innerBlocksTemplate !== [] || $innerBlocksTemplateLock !== null;
    }
}
