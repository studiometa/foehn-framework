<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The Timber class map, in a real WordPress with Timber.
 *
 * The unit suite reads the `timber/post/classmap` filter over stubs. Only a real site
 * shows which class Timber builds for a post when a post type and a Timber model of
 * the same name both map a class.
 */

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('builds the class of a Timber model over the class of its post type', function () {
    $output = (string) Site::wp('wp eval-file tests/framework-smoke/fixtures/timber-class-map.php');
    $result = json_decode($output, true);

    expect($result)->toBeArray($output);
    expect($result['post'])->toBe('SmokeClassMapProjectModel');
    expect($result['posts'])->toBe(['SmokeClassMapProjectModel']);
});
