<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;

/**
 * Static page cache.
 *
 * A portfolio is the case the page cache is made for: the pages change when a series
 * is published and not otherwise, and every visitor gets the same HTML.
 *
 * Configured and allowed in production only, which is the pair a project wants by
 * default — the feature is ready, and editing a Twig template locally still shows up
 * on the next reload rather than in eight hours. To watch it work here, add an
 * `app/page-cache.local.config.php` with `environments: ['local']`; the
 * environment's own file wins over this one. A purge fires on content changes, not
 * on template edits, so `wp foehn page-cache:clear` is the companion to that.
 *
 * Nonce caveat: a nonce frozen into a cached page expires with its 12–24 h window,
 * and a form submitted after that fails until the page is re-rendered. This site has
 * no forms; a site with one excludes those pages. See docs/guide/page-cache.md.
 */
return new PageCacheConfig(enabled: true, ttl: 8 * HOUR_IN_SECONDS, environments: ['production']);
