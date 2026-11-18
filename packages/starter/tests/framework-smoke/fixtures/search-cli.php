<?php

declare(strict_types=1);

/**
 * For `wp --require=…/search-cli.php foehn-search reindex`: the search document of the
 * smoke posts, for a command that WP-CLI runs.
 *
 * WP-CLI loads this file before WordPress, so the document waits for `after_wp_load`.
 */

WP_CLI::add_hook('after_wp_load', static function (): void {
    require __DIR__ . '/search-document.php';
});
