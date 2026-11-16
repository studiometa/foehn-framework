<?php

declare(strict_types=1);

/**
 * The breadcrumbs of one page, from PHP and from Twig.
 *
 * Run by `BreadcrumbsWithRankMathTest` through `wp eval-file`, with the query vars of
 * the page as a query string (`pagename=sample-page`) and the reader (`php` or `twig`).
 * Prints the items as JSON on one line, because `Site::wp()` hands back the last line.
 */

/** @var list<string> $args */
parse_str($args[0] ?? '', $vars);

// The main query of a request, with the globals Rank Math reads, such as `$post`.
wp($vars);

echo
    ($args[1] ?? 'php') === 'twig'
        ? \Timber\Timber::compile_string('{{ breadcrumbs()|json_encode }}')
        : json_encode(\Studiometa\Foehn\breadcrumbs())
;
