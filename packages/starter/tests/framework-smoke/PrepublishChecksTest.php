<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The accessibility checks of the pre-publish panel, enqueued in the block editor.
 *
 * The unit suite stubs `wp_enqueue_script()`. Only a real site shows that the script
 * the installer copied is the one the editor loads, with its configuration.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('enqueues the pre-publish checks in the block editor, with their configuration', function () {
    expect(Site::wp('wp eval-file tests/framework-smoke/fixtures/prepublish-checks.php'))
        ->toBe('enqueued=1 file=1 config=1');
});
