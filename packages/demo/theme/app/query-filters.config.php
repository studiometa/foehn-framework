<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\QueryFiltersConfig;

/**
 * The filters this site exposes beyond what WordPress already parses.
 *
 * Most of the projects archive needs nothing from here. `project_category` is a
 * registered public taxonomy, so `?project_category=still-life` — and the array a
 * checkbox group posts — is read by `WP_Query` on its own, and `orderby` is a public
 * query var for the same reason. A config file for either would be ceremony.
 *
 * `posts_per_page` is the exception, and the reason this file exists: it is a *private*
 * query var. WordPress ignores it from a URL, deliberately, because a visitor who can
 * set it can ask a site to render every post it has. Naming it here makes it public and
 * bounded in the same breath — three values, and a fourth is refused rather than
 * clamped.
 *
 * `QueryFiltersHook` has to be in the theme's `hooks` list for any of this to apply;
 * see `foehn.config.php`. The page cache keys these arguments separately, in
 * `page-cache.config.php` — the two files do not read each other, so a filter added
 * here and not named there is one whose every URL bypasses the cache.
 */
return new QueryFiltersConfig(
    publicVars: [
        // The archive shows three per page by default. A visitor can ask for six or
        // twelve, and for nothing else.
        'posts_per_page' => [3, 6, 12],
    ],
);
