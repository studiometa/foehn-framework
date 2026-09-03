<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The non-production indexing guard, on a real WordPress.
 *
 * The unit suite proves the four filters return the right values. What it cannot prove is
 * that WordPress reaches them at all — `wp_robots` only renders when a theme calls
 * `wp_head()`, `robots_txt` only runs when no physical `robots.txt` shadows it, and
 * `wp_sitemaps_enabled` only decides a 404 once core has built the sitemap routes. Each of
 * those is a way for a guard that tests green to be absent from the actual response.
 *
 * The ddev site runs `WP_ENVIRONMENT_TYPE=local`, so the guard is active here and these
 * assertions are the positive ones. There is no negative counterpart: proving production
 * is untouched needs an empty hook list, which is a unit assertion.
 *
 * Nothing here enables the page cache. The guard is registered whether the cache is on or
 * off, and the reason the meta tag matters more than the header — a cached page is served
 * without PHP running — is about a *stored* page rather than about this one.
 */
beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
    }
});

describe('a non-production site', function () {
    it('carries noindex and nofollow in the document itself', function () {
        // The one that survives the cache, and therefore the one that matters. A crawler
        // reading a stored page never sees a header PHP sends.
        $response = smokeGet('/');

        expect($response->status)->toBe(200);
        // Single quotes, because that is what core's `wp_robots()` emits — it builds the
        // tag with `esc_attr()` inside a single-quoted attribute. Matching double quotes
        // here passed review and failed against the real thing.
        expect($response->body)->toMatch("/<meta name='robots' content='[^']*noindex[^']*'/");
        expect($response->body)->toMatch("/<meta name='robots' content='[^']*nofollow[^']*'/");
    });

    it('keeps the directives that only describe an indexed page', function () {
        // `max-image-preview` says how to present a page that is in the index and has
        // nothing to say about one that is not, so the guard leaves it alone. Asserted
        // because the obvious implementation — replace the array — would drop it, and
        // nothing else would notice.
        expect(smokeGet('/')->body)->toMatch("/<meta name='robots' content='[^']*max-image-preview[^']*'/");
    });

    it('sends X-Robots-Tag on the response', function () {
        // Spelled exactly as a section response spells it: `header()` replaces a field
        // rather than appending to it, so two spellings would make the answer depend on
        // which of the two ran last.
        expect(smokeGet('/')->header('x-robots-tag'))->toBe('noindex, nofollow');
    });

    it('disallows the whole host through core\'s own do_robots()', function () {
        // Through WP-CLI rather than over HTTP, and the reason is worth writing down
        // because the obvious version of this test fails on a healthy machine.
        //
        // `robots.txt` is a virtual route: core answers it from `do_robots()`, which only
        // runs if the request reaches PHP. ddev's stock WordPress template carries a
        // `location = /robots.txt` with no `try_files`, inherited from an old
        // `global_restrictions.conf`, so nginx looks for a file, finds none, and answers
        // its own 404 without WordPress hearing about it. Asking for
        // `/index.php?robots=1` instead does not help either: core's canonical redirect
        // answers 301 back to `/robots.txt`, and the two bounce off each other.
        //
        // That template is not tracked here and is not something this repository ships —
        // the production image (`webdevops/php-nginx`) routes the path to PHP like any
        // other, and the specification calls `robots.txt` advisory and explicitly not the
        // primary protection. What is ours is that the filter is registered and returns
        // the right body in a real WordPress, so that is what this drives: core's own
        // `do_robots()`, in a booted site, through the hook chain a request would use.
        //
        // The script prints one line because `Site::wp()` hands back the last one.
        $script = 'tests/smoke/fixtures/do-robots.php';
        $reported = Site::wp('wp eval-file ' . escapeshellarg($script));

        expect($reported)->toBe(
            'agent=1 disallow=1 sitemap=0',
            'do_robots() did not produce the guard\'s body — got: ' . var_export($reported, true),
        );
    });

    it('serves no core sitemap', function () {
        // Without this, a crawler that ignored the directives above would still be handed
        // the complete list of URLs it was asked not to read.
        expect(smokeGet('/wp-sitemap.xml')->status)->toBe(404);
    });
});
