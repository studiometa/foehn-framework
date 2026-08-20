<?php

declare(strict_types=1);

/**
 * Point every absolute URL in the database at the URL this ddev project serves.
 *
 *     wp eval-file database/fix-urls.php [<url>]
 *
 * `demo.sql.gz` carries the URL of the site it was dumped from. Any other ddev
 * configuration — a renamed project, a router domain such as `*.studiometa.dev` —
 * serves the demo somewhere else, and every guid, menu item and serialized option in
 * the dump still names the old host. `DDEV_PRIMARY_URL` is what the container knows
 * it answers on, so it is the target unless one is passed as an argument.
 *
 * The old host cannot come from `get_option()`: wp-config defines `WP_HOME` and
 * `WP_SITEURL` from `.env`, and a constant beats the stored value. The `siteurl` row
 * is read straight out of the table instead, so the host that moves is the dump's and
 * not the environment's.
 *
 * Only the origin is replaced — scheme, host and port. Paths ride along, so a dump
 * whose `siteurl` ends in `/wp` needs no special case.
 *
 * Idempotent: with nothing to move, it changes nothing.
 */

/** @var list<string> $args */
$target = $args[0] ?? getenv('DDEV_PRIMARY_URL');

if (!is_string($target) || $target === '') {
    WP_CLI::error(
        'No target URL: DDEV_PRIMARY_URL is unset. Run this inside the web container, or pass the URL as an argument.',
    );
}

/**
 * Reduce a URL to scheme, host and port — the part every absolute URL in the
 * database shares.
 */
$origin = static function (string $url): string {
    $parts = parse_url($url);

    if (!isset($parts['scheme'], $parts['host'])) {
        WP_CLI::error(sprintf('Cannot read a scheme and a host out of "%s".', $url));
    }

    return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
};

global $wpdb;

$stored = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'siteurl'");

if (!is_string($stored) || $stored === '') {
    WP_CLI::error('No `siteurl` row in the options table — is the database imported?');
}

$from = $origin($stored);
$to = $origin($target);

if ($from === $to) {
    WP_CLI::success(sprintf('URLs already point at %s.', $to));

    return;
}

// --precise forces the PHP replacement rather than a SQL one, which is what walks
// into serialized option values instead of corrupting their length prefixes.
WP_CLI::runcommand(sprintf(
    'search-replace %s %s --all-tables --precise --report-changed-only',
    escapeshellarg($from),
    escapeshellarg($to),
));

// Pages cached under the old host would serve its URLs back for as long as they live.
WP_CLI::runcommand('foehn cache:clear');

WP_CLI::success(sprintf('URLs moved from %s to %s.', $from, $to));
