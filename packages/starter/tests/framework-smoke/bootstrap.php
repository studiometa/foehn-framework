<?php

declare(strict_types=1);

/**
 * Bootstrap for the framework smoke suite.
 *
 * Deliberately *not* `tests/php/bootstrap.php`. That one loads WordPress function stubs,
 * which exist so a unit test can call `add_action()` without WordPress. This suite talks
 * HTTP to a real WordPress and reads real files, so a stub in scope would be actively
 * misleading — a test could call `home_url()` and get `http://example.com` back while
 * believing it had asked the site.
 *
 * All it needs is the monorepo's autoloader, which knows `Studiometa\Foehn\Smoke\` for
 * the support classes beside it. This directory is export-ignored: the suite runs in the
 * monorepo only, against the starter, and a project created from the starter never sees
 * it.
 */
$autoload = dirname(__DIR__, 4) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, sprintf(
        "No autoloader for the smoke suite: %s does not exist.\nRun `composer install` at the monorepo root.\n",
        $autoload,
    ));

    exit(1);
}

require_once $autoload;
