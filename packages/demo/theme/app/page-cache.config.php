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
 * and a form submitted after that fails until the page is re-rendered. The filter
 * form on the projects archive carries no nonce — it is a GET form reading public
 * taxonomy terms, which is the whole reason it can be cached. A form that posts
 * something excludes its page. See docs/guide/page-cache.md.
 *
 * The two arguments the filter form emits are keyed, so a filtered archive is a
 * cached archive rather than a bypass. Both spellings a browser can produce reach the
 * same file: `?project_category[]=corridors&project_category[]=osaka` from the
 * checkbox group, and `?project_category=corridors,osaka` from a hand-written link.
 * Naming them here is a separate step from the template that emits them, and it has
 * to be: an argument this file does not name is one the cache never stores, which
 * looks like a slow page rather than an error.
 */
return new PageCacheConfig(
    enabled: true,
    ttl: 8 * HOUR_IN_SECONDS,
    environments: ['production'],
    cacheQueryArgs: [
        // Term slugs, one or several. The pattern is the floor the cache imposes on a
        // value that becomes part of a filename; a slug outside it bypasses.
        'project_category' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*$',
        // The two orders the form offers, and nothing else — `?orderby=rand` would be
        // a different page for every visitor and is not one of them.
        'orderby' => ['date', 'title'],
    ],
);
