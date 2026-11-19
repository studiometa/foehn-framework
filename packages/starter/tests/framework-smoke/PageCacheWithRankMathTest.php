<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The page cache, with Rank Math active.
 *
 * Two things Rank Math changes for the cache:
 *
 * - it turns off the core sitemap and publishes its own index, so the warm-up must read
 *   that index when the project names no sitemap;
 * - its redirections run in PHP, so a page stored before a redirection hides it until the
 *   page is purged.
 *
 * The plugin is installed with Composer, active for this file only and uninstalled
 * afterwards (see smokeActivatePlugin()).
 */

const RANK_MATH_REDIRECTION = 'wp eval-file tests/framework-smoke/fixtures/rank-math-redirection.php';

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    smokeActivatePlugin('seo-by-rank-math');
    // Rank Math loads its front end only once the registration is done or skipped.
    Site::wp('wp option update rank_math_registration_skip 1');
    // The redirections module is off on a new install, and its tables come with it.
    Site::wp(
        'wp eval '
            . escapeshellarg(
                "update_option('rank_math_modules', array_merge(get_option('rank_math_modules', []), ['redirections']));"
                . " \\RankMath\\Installer::create_tables(['redirections']);",
            ),
    );
    // Rank Math serves its sitemaps through rewrite rules, read only after a flush.
    Site::wp('wp rewrite flush');
    // A page of this file's own, so the test does not depend on the content of the site.
    Site::wp(
        'wp post create --post_type=page --post_status=publish --post_name=rank-math-smoke --post_title="Rank Math smoke"',
    );
    Site::enableCache();
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    smokeUninstallPlugin('seo-by-rank-math');
    Site::wp('wp option delete rank_math_registration_skip');
    Site::wp('wp post delete $(wp post list --post_type=page --name=rank-math-smoke --field=ID) --force');
    Site::wp('wp rewrite flush');
    Site::disableCache();
    Site::clearCache();
});

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');

    Site::wp(RANK_MATH_REDIRECTION . ' reset');
    Site::clearCache();
});

it('warms the pages of the Rank Math sitemap when the project names no sitemap', function () {
    $output = (string) Site::wp('wp foehn cache:warm --sync');
    // The warm-up reads the sitemap over HTTP, from inside the container.
    $sitemap = Site::wp("wp eval 'echo wp_remote_retrieve_response_code(wp_remote_get(rank_math_get_sitemap_url()));'");

    expect(is_file(Site::cacheFile('/hello-world/')))
        ->toBeTrue(sprintf('not warmed — %s The sitemap index answered %s.', $output, $sitemap));
});

it('purges the stored page of the source of a new redirection', function () {
    smokeWarm('/sample-page/');
    expect(is_file(Site::cacheFile('/sample-page/')))->toBeTrue('nothing was stored to purge');

    Site::wp(RANK_MATH_REDIRECTION . ' create sample-page /contact/');

    $response = smokeGet('/sample-page/');
    expect($response->status)->toBe(301, $response->describe());
});

it('purges the stored page of the source of a redirection that is turned on', function () {
    $id = (string) Site::wp(RANK_MATH_REDIRECTION . ' create rank-math-smoke /contact/ exact inactive');
    smokeWarm('/rank-math-smoke/');
    expect(is_file(Site::cacheFile('/rank-math-smoke/')))->toBeTrue('nothing was stored to purge');

    Site::wp(RANK_MATH_REDIRECTION . ' status ' . $id . ' active');

    $response = smokeGet('/rank-math-smoke/');
    expect($response->status)->toBe(301, $response->describe());
});

it('empties the cache for a redirection whose source is a pattern', function () {
    smokeWarm('/hello-world/');
    expect(is_file(Site::cacheFile('/hello-world/')))->toBeTrue('nothing was stored to purge');

    Site::wp(RANK_MATH_REDIRECTION . ' create ' . escapeshellarg('^hello-') . ' /contact/ regex');

    $response = smokeGet('/hello-world/');
    expect($response->status)->toBe(301, $response->describe());
});
