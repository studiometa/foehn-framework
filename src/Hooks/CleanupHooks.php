<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks;

use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsFilter;

/**
 * Common WordPress cleanup hooks.
 *
 * Removes unnecessary default WordPress output from wp_head:
 * - Generator meta tag
 * - Emoji scripts and styles
 * - REST API link
 * - oEmbed discovery links
 * - Feed links
 * - wlwmanifest link
 * - RSD link
 * - Shortlink
 *
 * Also cleans up empty paragraphs in content and archive title prefixes.
 */
final class CleanupHooks
{
    /**
     * Remove unnecessary WordPress head output.
     */
    #[AsAction('init')]
    public function cleanup(): void
    {
        // Remove WP generator meta tag
        remove_action('wp_head', 'wp_generator');

        // Remove emoji scripts and styles
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');

        // Remove REST API link
        remove_action('wp_head', 'rest_output_link_wp_head');

        // Remove oEmbed discovery links
        remove_action('wp_head', 'wp_oembed_add_discovery_links');

        // Remove feed links
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);

        // Remove wlwmanifest link
        remove_action('wp_head', 'wlwmanifest_link');

        // Remove RSD link
        remove_action('wp_head', 'rsd_link');

        // Remove shortlink
        remove_action('wp_head', 'wp_shortlink_wp_head');
    }

    /**
     * Remove empty paragraphs from content.
     */
    #[AsFilter('the_content', priority: 20)]
    public function cleanEmptyParagraphs(string $content): string
    {
        return (string) preg_replace('/<p>(\s|&nbsp;)*<\/p>/', '', $content);
    }

    /**
     * Remove archive title prefix (e.g. "Category:", "Tag:").
     */
    #[AsFilter('get_the_archive_title')]
    public function cleanArchiveTitlePrefix(string $title): string
    {
        return (string) preg_replace('/^[^:]+:\s*/', '', $title);
    }
}
