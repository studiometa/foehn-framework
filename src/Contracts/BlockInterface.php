<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Contracts;

use WP_Block;

/**
 * Interface for native Gutenberg blocks.
 *
 * Implement this interface to create Gutenberg blocks with the #[AsBlock] attribute.
 */
interface BlockInterface
{
    /**
     * Define block attributes schema.
     *
     * @return array<string, array<string, mixed>> Attributes definition
     */
    public static function attributes(): array;

    /**
     * Compose data for the view.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner block content
     * @param WP_Block $block Block instance
     * @return array<string, mixed> Context for the template
     */
    public function compose(array $attributes, string $content, WP_Block $block): array;

    /**
     * Render the block.
     *
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner block content
     * @param WP_Block $block Block instance
     * @return string Rendered HTML
     */
    public function render(array $attributes, string $content, WP_Block $block): string;
}
