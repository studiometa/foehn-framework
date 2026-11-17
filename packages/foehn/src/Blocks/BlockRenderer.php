<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use Studiometa\Foehn\Contracts\BlockInterface;
use Studiometa\Foehn\Contracts\InteractiveBlockInterface;
use WP_Block;
use WP_Block_Supports;
use WP_HTML_Tag_Processor;

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
            return $canvas ? $html : self::mergeSupportAttributes($html);
        });
    }

    /**
     * Put the block support attributes on the first tag of the rendered markup.
     *
     * They are what `get_block_wrapper_attributes()` prints: the classes and inline
     * styles of the colors, spacing, typography, border, alignment, anchor and
     * custom class supports. The editor merges them onto the template's root element
     * with useBlockProps, and this does the same on the front end. As in the editor,
     * the classes of both are kept, a support style comes after the template's own
     * style, and any other support attribute replaces the template's value.
     *
     * Core reads the block being rendered from `WP_Block_Supports::$block_to_render`,
     * which `WP_Block::render()` sets around the render callback.
     */
    private static function mergeSupportAttributes(string $html): string
    {
        $supports = WP_Block_Supports::get_instance()->apply_block_supports();

        if ($supports === []) {
            return $html;
        }

        $processor = new WP_HTML_Tag_Processor($html);

        if (!$processor->next_tag()) {
            return $html;
        }

        foreach ($supports as $name => $value) {
            if ($name === 'class') {
                foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                    $processor->add_class($class);
                }

                continue;
            }

            if ($name === 'style') {
                $own = $processor->get_attribute('style');
                $own = is_string($own) ? rtrim(trim($own), ';') : '';
                $processor->set_attribute('style', $own === '' ? $value : $own . '; ' . $value);

                continue;
            }

            $processor->set_attribute($name, $value);
        }

        return $processor->get_updated_html();
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
