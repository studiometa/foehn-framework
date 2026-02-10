<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsAction;

/**
 * Disable RSS/Atom feed links in wp_head.
 *
 * Removes both the main feed links and the extra feed links (comments,
 * categories, tags, etc.) from the HTML head.
 *
 * ⚠️  Only use this on sites that don't need RSS feeds (brochure sites,
 * headless setups). Blogs and news sites should keep feeds enabled.
 */
final class DisableFeeds
{
    #[AsAction('init')]
    public function removeFeedLinks(): void
    {
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }
}
