<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Blocks;

use Studiometa\WPTempest\Contracts\BlockInterface;
use Studiometa\WPTempest\Contracts\InteractiveBlockInterface;
use WP_Block;

/**
 * Handles rendering of native Gutenberg blocks.
 */
final class BlockRenderer
{
    /**
     * Render a native Gutenberg block.
     *
     * @param BlockInterface $block The block instance
     * @param array<string, mixed> $attributes Block attributes
     * @param string $content Inner block content
     * @param WP_Block $wpBlock WordPress block instance
     * @param string|null $interactivityNamespace Namespace for interactivity API
     * @return string Rendered HTML
     */
    public function render(
        BlockInterface $block,
        array $attributes,
        string $content,
        WP_Block $wpBlock,
        ?string $interactivityNamespace = null,
    ): string {
        // Handle interactivity state registration
        if ($block instanceof InteractiveBlockInterface && $interactivityNamespace !== null) {
            $this->registerInteractivityState($block, $interactivityNamespace);
        }

        // Render the block
        return $block->render($attributes, $content, $wpBlock);
    }

    /**
     * Register interactivity state for an interactive block.
     *
     * @param InteractiveBlockInterface $block
     * @param string $namespace
     */
    private function registerInteractivityState(InteractiveBlockInterface $block, string $namespace): void
    {
        if (!function_exists('wp_interactivity_state')) {
            return;
        }

        $state = $block::initialState();

        if (!empty($state)) {
            wp_interactivity_state($namespace, $state);
        }
    }
}
