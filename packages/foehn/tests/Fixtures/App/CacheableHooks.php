<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Studiometa\Foehn\Attributes\AsAction;

/**
 * The only class in tests/Fixtures/App, which stands in for a theme's app directory.
 *
 * Tests that scan a directory point at that one rather than at Fixtures itself,
 * which holds deliberately invalid classes a scan is meant to reject loudly.
 */
final class CacheableHooks
{
    #[AsAction('init')]
    public function onInit(): void {}
}
