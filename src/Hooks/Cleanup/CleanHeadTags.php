<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks\Cleanup;

use Studiometa\WPTempest\Attributes\AsAction;

/**
 * Remove obsolete and unnecessary tags from wp_head.
 *
 * Removes:
 * - wlwmanifest link (Windows Live Writer — discontinued)
 * - RSD link (Really Simple Discovery — legacy XML-RPC protocol)
 * - Shortlink (`?p=123` — redundant with pretty permalinks)
 * - REST API discovery link (the `<link rel="https://api.w.org/">` tag,
 *   not the REST API itself which continues to work)
 */
final class CleanHeadTags
{
    #[AsAction('init')]
    public function cleanup(): void
    {
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
    }
}
