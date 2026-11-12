<?php

declare(strict_types=1);

namespace Studiometa\Foehn\PageCache;

/**
 * Warm every sitemap URL, after a full flush emptied the cache.
 *
 * A job of its own rather than one `WarmUrl` per URL queued from {@see Purger}: the
 * flush runs on `shutdown` of a menu save or a plugin activation, and reading a sitemap
 * there would hold that request for as long as the sitemap takes to build.
 * {@see SitemapWarmer} reads it later, and queues the URLs it lists.
 */
final readonly class WarmSitemap {}
