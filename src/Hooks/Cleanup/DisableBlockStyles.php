<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsAction;

/**
 * Disable WordPress block editor (Gutenberg) styles.
 *
 * WordPress loads several stylesheets for the block editor that may not be
 * needed if your theme doesn't use Gutenberg blocks:
 *
 * - `wp-block-library` (~12KB): Core block styles (paragraphs, images, etc.)
 * - `wp-block-library-theme` (~1KB): Theme-specific block style overrides
 * - `classic-theme-styles` (~2KB): Compatibility styles for classic themes
 *
 * ⚠️  Only use this if your theme does NOT use Gutenberg blocks for content.
 * If editors use the block editor to create content, these styles are required
 * for proper rendering on the frontend.
 *
 * Safe to use when:
 * - Using ACF or custom fields exclusively for content
 * - Using a classic editor plugin
 * - Building a headless WordPress setup
 */
final class DisableBlockStyles
{
    #[AsAction('wp_enqueue_scripts', priority: 100)]
    public function disableBlockStyles(): void
    {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('classic-theme-styles');
    }
}
