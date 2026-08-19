<?php

declare(strict_types=1);

/**
 * Bootstrap for the smoke suite.
 *
 * Deliberately *not* `tests/php/bootstrap.php`. That one loads WordPress function stubs,
 * which exist so a unit test can call `add_action()` without WordPress. This suite talks
 * HTTP to a real WordPress and reads real files, so a stub in scope would be actively
 * misleading — a test could call `home_url()` and get `http://example.com` back while
 * believing it had asked the site.
 *
 * The monorepo autoloader is all that is needed, for the config classes the suite writes
 * into a generated config file and for the support classes beside it.
 */
require_once dirname(__DIR__, 4) . '/vendor/autoload.php';
