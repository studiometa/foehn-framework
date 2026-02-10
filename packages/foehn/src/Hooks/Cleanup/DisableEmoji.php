<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Disable WordPress emoji scripts and styles.
 *
 * Removes the inline emoji detection script and related styles from both
 * the front-end and admin. Also removes the emoji DNS prefetch hint and
 * the TinyMCE emoji plugin.
 *
 * Safe to use on all sites — browsers render native emojis without this script.
 */
final class DisableEmoji
{
    #[AsAction('init')]
    public function removeEmojiHooks(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    /**
     * Remove emoji CDN from DNS prefetch hints.
     *
     * @param list<string> $urls
     * @param string $relationType
     * @return list<string>
     */
    #[AsFilter('wp_resource_hints', acceptedArgs: 2)]
    public function removeEmojiDnsPrefetch(array $urls, string $relationType): array
    {
        if ($relationType !== 'dns-prefetch') {
            return $urls;
        }

        return array_values(array_filter(
            $urls,
            static fn(string $url): bool => !str_contains($url, 'w.org/images/core/emoji'),
        ));
    }

    /**
     * Remove TinyMCE emoji plugin.
     *
     * @param list<string> $plugins
     * @return list<string>
     */
    #[AsFilter('tiny_mce_plugins')]
    public function removeTinyMceEmoji(array $plugins): array
    {
        return array_values(array_diff($plugins, ['wpemoji']));
    }
}
