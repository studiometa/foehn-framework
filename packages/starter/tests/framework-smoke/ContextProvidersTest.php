<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Context providers for a view that a template renders with Timber itself.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('runs the providers for a view rendered with Timber::compile() and Timber::context()', function () {
    // The starter's GlobalContextProvider (`*`) puts `current_year` in the footer.
    expect(Site::wp('wp eval-file tests/framework-smoke/fixtures/timber-render.php'))->toBe('current_year=1');
});
