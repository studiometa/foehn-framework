<?php

declare(strict_types=1);

// The WordPress and ACF function stubs are the framework's, and stay private to
// it: they are executable fakes with a call recorder, not a published API. This
// suite runs from the monorepo, where they sit next door.
require_once dirname(__DIR__, 2) . '/foehn/tests/wp-stubs.php';

// In the monorepo, vendor/ is at the repository root.
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
