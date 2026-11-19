<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The opt-in `DisableAuthorSitemap` hook, with Rank Math active.
 *
 * The author sitemap lists the archive URL of each author, which holds the user slug:
 * a list of logins to try. The hook is turned on by a `foehn.local.config.php` that adds
 * it to the hooks of the starter. The core sitemap is off outside production, so only
 * the sitemap of Rank Math is checked here.
 *
 * The plugin is installed with Composer, active for this file only and uninstalled
 * afterwards (see smokeActivatePlugin()).
 */

const AUTHOR_SITEMAP_CONFIG = 'theme/app/foehn.local.config.php';

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    // Restored at the end, as VerificationTest does: the hook goes through a config file,
    // and the discovery cache keeps the hooks it found.
    $matches = [];
    preg_match('/(\d+)\/(\d+)/', (string) Site::wp('wp foehn discovery:status | grep "Locations cached:"'), $matches);
    $GLOBALS['foehn_author_sitemap_cache_was_warm'] = ($matches[2] ?? '0') !== '0' && $matches[1] === $matches[2];

    smokeActivatePlugin('seo-by-rank-math');
    // Rank Math loads its front end only once the registration is done or skipped.
    Site::wp('wp option update rank_math_registration_skip 1');
    // Rank Math sets `noindex` on author archives on a new install, which keeps them out
    // of its sitemap. A site that indexes them lists every author.
    Site::wp(
        'wp eval '
            . escapeshellarg(
                "update_option('rank-math-options-titles', ['author_custom_robots' => 'off']"
                . " + get_option('rank-math-options-titles', []));",
            ),
    );
    // Rank Math reads its sitemap rewrite rules only after a flush.
    Site::wp('wp rewrite flush');
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    is_file(Site::path(AUTHOR_SITEMAP_CONFIG)) && unlink(Site::path(AUTHOR_SITEMAP_CONFIG));
    Site::wp(
        $GLOBALS['foehn_author_sitemap_cache_was_warm'] ?? false
            ? 'wp foehn discovery:generate'
            : 'wp foehn discovery:clear',
    );
    smokeUninstallPlugin('seo-by-rank-math');
    Site::wp('wp option delete rank_math_registration_skip');
    Site::wp('wp rewrite flush');
});

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');

    // Rank Math keeps the sitemaps it built in files, which outlive the plugin.
    Site::wp("wp eval '\\RankMath\\Sitemap\\Cache::invalidate_storage();'");
});

it('lists the authors in the sitemap of Rank Math without the hook', function () {
    is_file(Site::path(AUTHOR_SITEMAP_CONFIG)) && unlink(Site::path(AUTHOR_SITEMAP_CONFIG));
    Site::wp('wp foehn discovery:clear');

    expect(smokeGet('/sitemap_index.xml')->body)->toContain('author-sitemap.xml');
    expect(smokeGet('/author-sitemap.xml')->status)->toBe(200);
});

it('removes the author sitemap of Rank Math with the hook', function () {
    // Rank Math keeps the author sitemap in its cache, and serves it from there before it
    // asks whether the sitemap is on.
    expect(smokeGet('/author-sitemap.xml')->status)->toBe(200);

    file_put_contents(Site::path(AUTHOR_SITEMAP_CONFIG), <<<'PHP'
        <?php

        declare(strict_types=1);

        // Written by the smoke suite, removed when it finishes.
        $config = require __DIR__ . '/foehn.config.php';

        return new Studiometa\Foehn\Config\FoehnConfig(
            discoveryCacheStrategy: $config->discoveryCacheStrategy,
            hooks: [...$config->hooks, Studiometa\Foehn\Hooks\Security\DisableAuthorSitemap::class],
        );
        PHP);
    // The discovery cache keeps the hooks it found, and opcache ignores a file younger
    // than two seconds.
    Site::wp('wp foehn discovery:clear');
    sleep(3);

    $index = smokeGet('/sitemap_index.xml');
    expect($index->status)->toBe(200);
    expect($index->body)->not->toContain('author-sitemap.xml');
    expect(smokeGet('/author-sitemap.xml')->status)->toBe(404);
});
