<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * A settings page field that the code sets, in a real WordPress.
 *
 * The unit suite draws the field over stubs. Only a real site runs the
 * `pre_option_{$option}` filters, `admin_menu` and `do_settings_sections()`.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('shows a value that a pre_option filter sets, read-only', function () {
    $output = (string) Site::wp('wp eval-file tests/framework-smoke/fixtures/settings-read-only.php');
    $result = json_decode($output, true);

    expect($result)->toBeArray($output);
    expect($result['token'])->toBe(['value' => true, 'readonly' => true]);
    expect($result['flag'])->toBe(['checked' => true, 'disabled' => true]);
    // A field that no filter sets stays editable.
    expect($result['free'])->toBe(['found' => true, 'locked' => false]);
    expect($result['note'])->toBe(2);
});
