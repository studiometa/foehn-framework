<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * Keyed query args, through real nginx.
 *
 * This file exists for one assertion: `?page=2&lang=fr` and `?lang=fr&page=2` are one
 * page, so they must be one file, and both spellings must be served by nginx rather than
 * quietly falling through to PHP. Nothing in a unit test can show that — the property is a
 * claim about two independent implementations of the same algorithm, one of them a
 * generated config file, and only a real server can settle it.
 *
 * The policy differs from the starter's own, so the include is regenerated here the way a
 * deploy would regenerate it, and put back afterwards.
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::enableCache([
        'cacheQueryArgs' => "['page' => '^[0-9]{1,6}\$', 'lang' => '^[a-z]{2}\$', "
            . "'genre' => '^[a-z0-9-]+(?:,[a-z0-9-]+)*\$']",
    ]);
    Site::wp("wp rewrite structure '/%postname%/' --hard");
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
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — start it in packages/starter and try again.');
    }

    Site::clearCache();
});

describe('keyed query args', function () {
    it('serves a keyed arg from nginx rather than falling through to PHP', function () {
        [$first, $second] = smokeGetTwice('/?page=2');

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'nginx');
        expectSameBody($first, $second, 'nginx served bytes that are not the ones PHP stored');
    });

    it('names the file after the args, in the configuration order', function () {
        smokeWarm('/?lang=fr&page=2');

        // Configuration order, not request order: lang before page, whichever way round
        // the request wrote them.
        expect(Site::cacheFile('/', 'index__lang=fr&page=2&.html'))->toBeFile();
    });

    it('serves both spellings of one URL from one file', function () {
        // The assertion this whole file is for.
        smokeWarm('/?page=2&lang=fr');

        $reversed = smokeGet('/?lang=fr&page=2');

        expectCache($reversed, 'HIT', 'nginx');
        expect(Site::cachedPages())->toHaveCount(1);
    });

    it('serves a multi-value filter from nginx', function () {
        // The comma form is what the query filters emit and what `$arg_genre` can read,
        // so it takes the fast path like any other keyed value.
        [$first, $second] = smokeGetTwice('/?genre=rock,jazz');

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'nginx');
        expect(Site::cacheFile('/', 'index__genre=rock,jazz&.html'))->toBeFile();
    });

    it('serves the bracketed form from the drop-in, out of the same file', function () {
        // nginx has no `$arg_genre[]`, so it declines and passes the request to PHP
        // rather than reading `$arg_genre`, finding it empty and serving the unfiltered
        // page. PHP joins the members and lands on the file the comma form wrote — so
        // this is a HIT that never touched the theme, and no second file appears.
        smokeWarm('/?genre=rock,jazz');

        $bracketed = smokeGet('/?genre[]=rock&genre[]=jazz');

        expectCache($bracketed, 'HIT', 'php');
        expect(Site::cachedPages())->toHaveCount(1);
    });

    it('keeps a keyed arg apart from the page without it', function () {
        smokeWarm('/');
        smokeWarm('/?page=2');

        expect(Site::cachedPages())
            ->toContain(Site::host() . '/index.html')
            ->toContain(Site::host() . '/index__page=2&.html');
    });

    it('decides every other query string the way the writer does', function (string $query, string $state) {
        // Both files a HIT could come from are warm, so a MISS here means nginx computed a
        // name PHP did not — which is the disagreement this file is watching for.
        smokeWarm('/');
        smokeWarm('/?page=2');

        expectCache(smokeGet('/' . $query), $state);
    })->with([
        'a keyed arg with no value keys as no query' => ['?page=', 'HIT'],
        'a keyed arg beside a tracking one' => ['?page=2&utm_source=x', 'HIT'],
        // nginx reads the first `page=`, PHP the last. Neither guesses.
        'a repeated keyed arg' => ['?page=1&page=2', 'BYPASS'],
        'a repeated keyed arg whose first value is empty' => ['?page=&page=2', 'BYPASS'],
        'a value its pattern rejects' => ['?page=abc', 'BYPASS'],
        'a value that would leave the cache directory' => ['?page=../../etc/passwd', 'BYPASS'],
        'an arg nobody configured' => ['?foo=bar', 'BYPASS'],
        'a keyed arg beside an unknown one' => ['?page=2&foo=bar', 'BYPASS'],
    ]);
});
