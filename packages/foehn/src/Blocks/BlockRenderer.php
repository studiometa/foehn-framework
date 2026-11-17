<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
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
     * @param bool $isContainer Whether the block holds inner blocks
     * @return string Rendered HTML
     */
    public function render(
        BlockInterface $block,
        array $attributes,
        string $content,
        WP_Block $wpBlock,
        ?string $interactivityNamespace = null,
        bool $isContainer = false,
    ): string {
        // Handle interactivity state registration
        if ($block instanceof InteractiveBlockInterface && $interactivityNamespace !== null) {
            $this->registerInteractivityState($block, $interactivityNamespace);
        }

        return EditorCanvas::render(static function (bool $canvas) use (
            $block,
            $attributes,
            $content,
            $wpBlock,
            $isContainer,
        ): string {
            // On the editor canvas the inner blocks are live editor components, not
            // saved markup: the template prints a marker where the editor mounts them.
            if ($canvas && $isContainer) {
                $content = EditorCanvas::INNER_BLOCKS;
            }

            $html = $block->render($attributes, $content, $wpBlock);

            // On the canvas the editor puts the same attributes on the root with useBlockProps.
            return $canvas ? $html : BlockSupports::merge($html);
        });
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
