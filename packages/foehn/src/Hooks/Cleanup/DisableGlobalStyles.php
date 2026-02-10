<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsAction;

/**
 * Disable WordPress global styles and SVG filters.
 *
 * WordPress 5.9+ injects inline `<style>` blocks for global styles and
 * SVG markup for duotone filters on every page load. This can add 10-50KB
 * of unused CSS/SVG to pages that don't use these features.
 *
 * This class removes:
 * - Inline global styles CSS (`wp_enqueue_global_styles`)
 * - SVG duotone filter definitions (`wp_body_open` SVG output)
 * - Global styles custom CSS (`wp_enqueue_global_styles_custom_css`)
 *
 * ⚠️  Only use this if your theme does not rely on `theme.json` presets
 * for colors, typography, spacing, or duotone image filters. Classic themes
 * and themes with custom CSS can safely disable this.
 */
final class DisableGlobalStyles
{
    #[AsAction('init')]
    public function disableGlobalStyles(): void
    {
        // Remove inline global styles
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);

        // Remove SVG duotone filters
        remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');

        // Remove global styles custom CSS (WP 6.7+)
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles_custom_css');
    }
}
