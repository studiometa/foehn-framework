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
 * carry one — see docs/guide/page-cache.md.
 */
return new PageCacheConfig(enabled: true, ttl: 8 * HOUR_IN_SECONDS, environments: ['production']);
