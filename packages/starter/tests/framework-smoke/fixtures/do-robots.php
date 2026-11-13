<?php

declare(strict_types=1);

/**
 * What core's `do_robots()` produces on this site, as one line.
 *
 * Run by `IndexingProtectionTest` through `wp eval-file`, because `/robots.txt` cannot be
 * reached over HTTP on a ddev site — see the comment on the test for why, and why that is
 * a web-server template rather than anything this repository ships.
 *
 * `do_robots()` rather than a bare `apply_filters('robots_txt', …)`: the question is
 * whether the guard is registered on the hook core actually uses, which a direct
 * `apply_filters()` call would answer yes to even if nothing had wired it up.
 *
 * One line of output, because `Site::wp()` hands back the last line of a command.
 */

ob_start();
do_robots();
$body = (string) ob_get_clean();

printf(
    'agent=%d disallow=%d sitemap=%d',
    (int) str_contains($body, 'User-agent: *'),
    (int) str_contains($body, 'Disallow: /'),
    // Core's own body advertises the sitemap. The guard replaces it rather than appending
    // to it, because a file that says `Disallow: /` and then points at an index of every
    // URL on the site is a mixed message.
    (int) str_contains($body, 'Sitemap:'),
);
