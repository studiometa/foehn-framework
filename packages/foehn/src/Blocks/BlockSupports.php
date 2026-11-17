<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Blocks;

use WP_Block_Supports;
use WP_HTML_Tag_Processor;

/**
 * Puts the block support attributes on the root element of a block's rendered markup.
 *
 * They are what `get_block_wrapper_attributes()` prints: the classes and inline
 * styles of the colors, spacing, typography, border, alignment, anchor and custom
 * class supports. The block editor merges them onto the root element with
 * useBlockProps, and this does the same on the front end. As in the editor, the
 * classes of both are kept, a support style comes after the template's own style,
 * and any other support attribute replaces the template's value.
 *
 * Core reads the block being rendered from `WP_Block_Supports::$block_to_render`,
 * which `WP_Block::render()` sets around the render callback. Outside a block
 * render, core answers no attributes and the markup is returned untouched.
 */
final class BlockSupports
{
    /**
     * Merge the support attributes of the block being rendered onto the first tag of `$html`.
     */
    public static function merge(string $html): string
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
}
