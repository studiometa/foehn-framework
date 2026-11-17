<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The theme's SVG icons, registered with the WordPress 7.1 Icons API.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('registers the icons of assets/icons/ and prints one with icon()', function () {
    expect(Site::wp('wp eval-file tests/framework-smoke/fixtures/icons.php'))->toBe('icon=1');
});
