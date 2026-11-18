<?php

declare(strict_types=1);

// The WordPress and Polylang function stubs of the framework. This suite runs from the
// monorepo, where they sit next door.
require_once dirname(__DIR__, 2) . '/foehn/tests/wp-stubs.php';
require_once dirname(__DIR__, 2) . '/foehn/tests/polylang-stubs.php';
require_once __DIR__ . '/places-stubs.php';

// In the monorepo, vendor/ is at the repository root.
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
