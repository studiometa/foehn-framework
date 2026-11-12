<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * Static page cache.
 *
 * Enabled here but allowed in production only, which is the pair of settings a project
 * wants by default: the feature is configured and ready, and a local edit to a template
 * still shows up on the next reload rather than in eight hours.
 *
 * To try it locally, add an `app/page-cache.local.config.php` with `environments:
 * ['local']` — the environment's own file wins over this one. Remember that a purge
 * fires on content changes, not on template edits.
 *
 * Nonce caveat: a nonce frozen into a cached page expires with its 12–24 h window, and a
 * form submitted after that fails until the page is re-rendered. Exclude the pages that
 * carry one (#nonces in the guide below).
 *
 * Other options, off here. The anchors are sections of the guide:
 * https://studiometa.github.io/foehn-framework/guide/page-cache
 *
 * - `browserMaxAge` or `cacheControl`: the `Cache-Control` header of a hit (#cache-control).
 * - `responseHeaders`: headers a hit must send, such as the security headers. A hit
 *   served by nginx does not send the `add_header` lines of the server block
 *   (#server-level-headers-and-nginx).
 * - `warmAfterPurge`, `warmSitemap`, `warmExclude`: request the purged pages again, and
 *   choose the sitemap and the URLs to warm. Needs Action Scheduler (#warming).
 * - `storage: PageCacheStorage::Bucket`: one cache in the uploads bucket for a site with
 *   more than one instance. Each deploy must then run `wp foehn cache:clear`
 *   (#storing-pages-in-the-bucket).
 */
return new PageCacheConfig(enabled: true, ttl: 8 * HOUR_IN_SECONDS, environments: ['production']);
