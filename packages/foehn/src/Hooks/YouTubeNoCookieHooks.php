<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks;

use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Replace YouTube embeds with youtube-nocookie.com for GDPR compliance.
 *
 * Applies to:
 * - Post content (the_content)
 * - ACF oEmbed fields (acf/format_value/type=oembed)
 */
final class YouTubeNoCookieHooks
{
    /**
     * Replace youtube.com/embed with youtube-nocookie.com/embed in content.
     */
    #[AsFilter('the_content')]
    public function replaceInContent(string $content): string
    {
        return $this->replaceYouTubeUrls($content);
    }

    /**
     * Replace youtube.com/embed with youtube-nocookie.com/embed in ACF oEmbed fields.
     */
    #[AsFilter('acf/format_value/type=oembed')]
    public function replaceInAcfOembed(string $value): string
    {
        return $this->replaceYouTubeUrls($value);
    }

    /**
     * Perform the URL replacement.
     */
    private function replaceYouTubeUrls(string $content): string
    {
        return str_replace('youtube.com/embed', 'youtube-nocookie.com/embed', $content);
    }
}
