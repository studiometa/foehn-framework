<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Security;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Prevent WordPress version disclosure.
 *
 * Removes the WordPress version from:
 * - The `<meta name="generator">` tag in wp_head
 * - The `?ver=` query string on enqueued scripts and styles
 * - The RSS feed generator tag
 *
 * Hiding the version makes it slightly harder for attackers to identify
 * which vulnerabilities apply to your installation.
 */
final class DisableVersionDisclosure
{
    /**
     * Remove the generator meta tag from wp_head.
     */
    #[AsAction('init')]
    public function removeGeneratorTag(): void
    {
        remove_action('wp_head', 'wp_generator');
    }

    /**
     * Remove version query string from scripts.
     */
    #[AsFilter('script_loader_src')]
    public function removeScriptVersion(string $src): string
    {
        return $this->removeVersionQueryString($src);
    }

    /**
     * Remove version query string from styles.
     */
    #[AsFilter('style_loader_src')]
    public function removeStyleVersion(string $src): string
    {
        return $this->removeVersionQueryString($src);
    }

    /**
     * Return empty string for the generator tag in RSS feeds.
     */
    #[AsFilter('the_generator')]
    public function removeRssGenerator(): string
    {
        return '';
    }

    /**
     * Remove the `ver` query parameter from a URL.
     */
    private function removeVersionQueryString(string $src): string
    {
        if (!str_contains($src, 'ver=')) {
            return $src;
        }

        // Remove ver= when it's the only or last parameter
        $src = (string) preg_replace('/([?&])ver=[^&]*$/', '', $src);

        // Remove ver= when followed by other parameters
        return (string) preg_replace('/([?&])ver=[^&]*&/', '$1', $src);
    }
}
