<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Section responses, through real nginx.
 *
 * `?foehn_sections=posts` returns the HTML of one named region of a page instead of the
 * whole document. It used to carry `Cache-Control: private, no-store` and to be a bypass
 * in all four readers, which meant that filtering or paginating in place re-rendered the
 * page every time — on a site whose whole point is that pages come off a file.
 *
 * Two things need a real server to settle, and neither can be shown in a unit test:
 *
 * **nginx keys the parameter the way PHP does.** `foehn_sections` is a keyed query arg
 * now, so the generated snippet unrolls it like `page` or `lang` and has to arrive at the
 * same filename the writer chose.
 *
 * **The `noindex` survives the fast path.** The drop-in replays the headers a response
 * recorded; nginx cannot read a stored header at all, so it derives this one from
 * `$arg_foehn_sections`. A fragment served without it would be indexed as if it were a
 * page — and it is precisely the cached responses, the ones nobody looks at again, where
 * that would go unnoticed.
 *
 * The starter's own policy already keys the parameter, so the include is regenerated
 * rather than reconfigured, and put back afterwards.
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    // Two options this file depends on, set rather than assumed and put back afterwards.
    //
    // `show_on_front` because the section under test is declared in `pages/archive.twig`,
    // which is what `/` renders when the front page is the blog — a database left with a
    // static front page renders `pages/page.twig`, and every request below would 404 for a
    // reason that has nothing to do with the cache.
    //
    // `blog_public` because WordPress sends an `X-Robots-Tag` of its own on every page of
    // a site marked private, and the assertion that a *page* carries none would then be
    // testing the option rather than the snippet.
    $GLOBALS['foehn_smoke_options'] = [];

    foreach (['show_on_front' => 'posts', 'blog_public' => '1'] as $option => $value) {
        $GLOBALS['foehn_smoke_options'][$option] = Site::wp('wp option get ' . $option);
        Site::wp(sprintf('wp option update %s %s', $option, escapeshellarg($value)));
    }

    Site::enableCache();
    Site::useNginx(true);

    $GLOBALS['foehn_smoke_nginx_include'] = Site::generateNginxInclude();
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::restoreNginxInclude((string) ($GLOBALS['foehn_smoke_nginx_include'] ?? ''));
    Site::disableCache();
    Site::clearCache();

    /** @var array<string, string|null> $options */
    $options = $GLOBALS['foehn_smoke_options'] ?? [];

    foreach ($options as $option => $value) {
        if (is_string($value) && $value !== '') {
            Site::wp(sprintf('wp option update %s %s', $option, escapeshellarg($value)));
        }
    }
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
    }

    Site::clearCache();
});

describe('cached section responses', function () {
    it('stores a section response and serves the second hit from nginx', function () {
        [$first, $second] = smokeGetTwice('/?foehn_sections=posts');

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'nginx');
        expectSameBody($first, $second, 'nginx served bytes that are not the ones PHP stored');
        expect($second->status)->toBe(200);
    });

    it('names the file after the selection, beside the page it came from', function () {
        smokeWarm('/');
        smokeWarm('/?foehn_sections=posts');

        expect(Site::cachedPages())
            ->toContain(Site::host() . '/index.html')
            ->toContain(Site::host() . '/index__foehn_sections=posts&.html');
    });

    it('stores the fragment rather than the page it was cut from', function () {
        // The body rule a page gets — at least 255 bytes and ending in `</html>` — is not
        // the rule a fragment gets, and getting that wrong would store the whole document
        // under the section's name.
        $response = smokeWarm('/?foehn_sections=posts');

        expect($response->body)->toContain('data-foehn-section="posts"')->not->toContain('<html');
    });

    it('keeps a cached fragment out of the index, whichever reader answered', function () {
        // The assertion this file is really for. PHP sets the header; nginx has no stored
        // header to replay and has to derive the same one from the query string.
        [$first, $second] = smokeGetTwice('/?foehn_sections=posts');

        expectCache($second, 'HIT', 'nginx');
        expect($first->header('x-robots-tag'))->toBe('noindex, nofollow');
        expect($second->header('x-robots-tag'))->toBe('noindex, nofollow');
    });

    it('does not tell the world no-store for a response it stores', function () {
        // The bug this pins: rendering runs with `foehn_sections` hidden from `$_SERVER`,
        // and while the response was built inside that window the cache rules saw a page,
        // judged the fragment incomplete against the page's body rule, and answered
        // `no-store` — for a body the recorder then wrote to disk. A CDN honouring that
        // header would have undone the fast path entirely.
        [$first, $second] = smokeGetTwice('/?foehn_sections=posts');

        expect($first->header('cache-control') ?? '')->not->toContain('no-store');
        expectCache($second, 'HIT');
        expect($second->header('cache-control'))->toContain('must-revalidate');
    });

    it('sends no noindex on the page itself', function () {
        // The header is conditional on `$arg_foehn_sections`, and an empty nginx variable
        // is a header nginx does not send. If that stopped holding, every cached page on
        // the site would tell search engines to drop it.
        $page = smokeWarm('/');

        expectCache($page, 'HIT', 'nginx');
        expect($page->header('x-robots-tag'))->toBeNull();
    });

    it('stores nothing for a selection no page declared', function () {
        // The bound on the key space: an undeclared name is a 400 or a 404, and only 200s
        // are stored — so no crawler can fill the disk with section files.
        $response = smokeGet('/?foehn_sections=nothing-here');

        expect($response->status)->toBe(404);
        expect(Site::cachedPages())->toBeEmpty();
    });

    it('stores nothing for a selection the parser refuses', function () {
        $response = smokeGet('/?foehn_sections=Posts');

        expect($response->status)->toBe(400);
        expect(Site::cachedPages())->toBeEmpty();
    });
});
