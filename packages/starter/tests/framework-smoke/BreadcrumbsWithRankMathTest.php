<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The `breadcrumbs()` helper and Twig function, with Rank Math active.
 *
 * The plugin is installed with Composer, active for this file only and uninstalled
 * afterwards (see smokeActivatePlugin()).
 */

const BREADCRUMBS = 'wp eval-file tests/framework-smoke/fixtures/breadcrumbs.php';

/**
 * Turn the breadcrumbs of Rank Math on or off.
 */
function rankMathBreadcrumbs(bool $on): void
{
    Site::wp(
        'wp eval '
            . escapeshellarg(
                "update_option('rank-math-options-general', ['breadcrumbs' => '"
                . ($on ? 'on' : 'off')
                . "'] + get_option('rank-math-options-general', []));",
            ),
    );
}

/**
 * @return list<array{label: string, url: string|null}>|null
 */
function breadcrumbsOf(string $query, string $reader = 'php'): ?array
{
    $output = Site::wp(BREADCRUMBS . ' ' . escapeshellarg($query) . ' ' . $reader);

    return $output === null ? null : json_decode($output, true);
}

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    smokeActivatePlugin('seo-by-rank-math');
    // Rank Math loads its front end only once the registration is done or skipped.
    Site::wp('wp option update rank_math_registration_skip 1');
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    smokeUninstallPlugin('seo-by-rank-math');
    Site::wp('wp option delete rank_math_registration_skip');
});

beforeEach(function () {
    Site::isRunning() || $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');

    $this->home = Site::url();
});

it('gives the trail of a page, the current page without a URL', function () {
    rankMathBreadcrumbs(true);

    expect(breadcrumbsOf('pagename=sample-page'))->toBe([
        ['label' => 'Home', 'url' => $this->home],
        ['label' => 'Sample Page', 'url' => null],
    ]);
});

it('gives the same trail from Twig', function () {
    rankMathBreadcrumbs(true);

    expect(breadcrumbsOf('pagename=sample-page', 'twig'))->toBe([
        ['label' => 'Home', 'url' => $this->home],
        ['label' => 'Sample Page', 'url' => null],
    ]);
});

it('gives the category of a post in its trail', function () {
    rankMathBreadcrumbs(true);

    $trail = breadcrumbsOf('name=hello-world');

    expect(array_column($trail ?? [], 'label'))->toBe(['Home', 'Uncategorized', 'Hello world!']);
    expect($trail[1]['url'] ?? null)->toContain('/category/uncategorized/');
});

it('gives no item when the breadcrumbs of Rank Math are off', function () {
    rankMathBreadcrumbs(false);

    expect(breadcrumbsOf('pagename=sample-page'))->toBe([]);
});
