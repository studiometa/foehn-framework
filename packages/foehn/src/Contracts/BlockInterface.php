<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

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
     * May return a plain array or an Arrayable DTO (which will be
     * flattened to array before passing to render()).
     *
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner block content
     * @param WP_Block $block Block instance
     * @return array<string, mixed>|Arrayable Context for the template
     */
    public function compose(array $attributes, string $content, WP_Block $block): array|Arrayable;

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
