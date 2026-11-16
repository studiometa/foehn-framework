<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The non-production indexing guard, with Rank Math active.
 *
 * Rank Math prints its own robots meta tag and removes every `wp_robots` filter, the
 * guard's included. Yoast needs no test of its own: it goes through `wp_robots`, where
 * the guard runs last.
 *
 * The plugin is installed from wordpress.org for this file and removed afterwards.
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::wp('wp plugin install seo-by-rank-math --activate');
    // Rank Math loads its front end only once the registration is done or skipped.
    Site::wp('wp option update rank_math_registration_skip 1');
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::wp('wp plugin uninstall seo-by-rank-math --deactivate');
    Site::wp('wp option delete rank_math_registration_skip');
});

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
});

it('carries noindex and nofollow in the meta tag that Rank Math prints', function () {
    $body = smokeGet('/')->body;

    expect($body)->toContain('rank-math');
    expect($body)->toMatch('/<meta name="robots" content="[^"]*noindex[^"]*"/');
    expect($body)->toMatch('/<meta name="robots" content="[^"]*nofollow[^"]*"/');
});
