<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsAction;

/**
 * Disable oEmbed discovery links in wp_head.
 *
 * Removes the `<link rel="alternate" type="application/json+oembed">` tags
 * that allow other sites to embed your content. The WordPress oEmbed
 * consumer (embedding external content in your posts) is not affected.
 *
 * ⚠️  Only use this if your site does not need to be embedded by other sites.
 */
final class DisableOembed
{
    #[AsAction('init')]
    public function disableOembedDiscovery(): void
    {
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
    }
}
