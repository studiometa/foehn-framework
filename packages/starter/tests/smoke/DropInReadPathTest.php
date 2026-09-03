<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The same cache, read by `wp-content/advanced-cache.php` with the nginx include removed.
 *
 * This file is what keeps the nginx assertions honest. Without it, every one of them could
 * be passing on a snippet that never matches, with the PHP drop-in quietly covering for
 * it — both readers answer HIT, and only `X-Foehn-Cache-Via` tells them apart.
 *
 * It also covers the two things the drop-in can do that no server config can: enforce the
 * TTL, because neither `try_files` nor `mod_rewrite` can look at a file's age, and answer
 * a conditional request with a 304.
 *
 * The include is removed once for this file and put back afterwards, because reloading
 * nginx per test would dominate the run.
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::enableCache();
    Site::useNginx(false);
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::useNginx(true);
    Site::disableCache();
    Site::clearCache();
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — run `ddev start` and try again.');
    }

    Site::clearCache();
});

describe('drop-in read path', function () {
    it('serves a stored page before WordPress boots', function () {
        [$first, $second] = smokeGetTwice('/');

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'php');
        expectSameBody($first, $second, 'the drop-in served bytes that are not the ones it stored');
    });

    it('is the reader answering, which is how we know nginx was not', function () {
        // The assertion that gives the nginx suite its meaning.
        expect(smokeWarm('/')->via())->toBe('php');
    });

    it('reads one file for two ignored args in either order', function () {
        smokeWarm('/');

        $a = smokeGet('/?utm_source=a&utm_medium=b');
        $b = smokeGet('/?utm_medium=b&utm_source=a');

        expectCache($a, 'HIT', 'php');
        expectCache($b, 'HIT', 'php');
        expectSameBody($a, $b, 'the drop-in read different files for the two argument orders');
    });

    it('decides a query string the same way nginx does', function (string $query, string $state) {
        smokeWarm('/');

        expectCache(smokeGet('/' . $query), $state);
    })->with([
        'one tracking arg' => ['?utm_source=x', 'HIT'],
        'a traversal inside a tracking arg' => ['?utm_source=../../etc/passwd', 'HIT'],
        'an arg nobody ignores' => ['?foo=bar', 'BYPASS'],
        'pagination and a language' => ['?page=2&lang=fr', 'BYPASS'],
        'the same two, reversed' => ['?lang=fr&page=2', 'BYPASS'],
        'a repeated arg' => ['?page=1&page=2', 'BYPASS'],
        'an arg with no value' => ['?page=', 'BYPASS'],
        'a traversal in an arg nobody ignores' => ['?page=../x', 'BYPASS'],
    ]);

    it('bypasses a request that carries a login cookie', function () {
        smokeWarm('/');

        $response = smokeGet('/', ['wordpress_logged_in_smoke' => '1']);

        expectCache($response, 'BYPASS', 'php');
        expect($response->reason())->toBe('cookie');
    });

    it('answers a conditional request with a 304, which no server snippet does here', function () {
        $warm = smokeWarm('/');
        $etag = $warm->header('etag');

        expect($etag)->not->toBeNull('the drop-in sent no ETag to revalidate against');

        $revalidated = Studiometa\Foehn\Smoke\Support\Client::get(
            Site::url() . '/',
            [],
            ['If-None-Match' => (string) $etag],
        );

        expect($revalidated->status)->toBe(304);
        expect($revalidated->body)->toBe('');
    });
});

describe('drop-in read path: the TTL', function () {
    afterEach(function () {
        // Guarded on the site, not only for speed. `afterEach` runs for a case
        // `beforeEach` skipped, and `enableCache()` writes a config file into the
        // project and then waits out opcache's file-update protection — so without
        // this, merely collecting the suite on a machine with no ddev left a
        // generated config behind and spent two seconds per case doing it.
        if (!Site::isRunning()) {
            return;
        }

        Site::enableCache();
    });

    it('stops serving a page older than the TTL, which nginx cannot do', function () {
        // `try_files` and `mod_rewrite` have no way to check a file's age. On this path the
        // TTL is exact; on theirs the hourly sweep is the bound.
        Site::enableCache(['ttl' => '3600']);
        smokeWarm('/');

        $file = Site::cacheFile('/');

        expect($file)->toBeFile();
        expect(touch($file, time() - 7200))->toBeTrue();

        $response = smokeGet('/');

        expectCache($response, 'MISS', 'php');
        expect($response->reason())->toBe('expired');
    });

    it('keeps a page inside the TTL', function () {
        Site::enableCache(['ttl' => '3600']);
        smokeWarm('/');

        expectCache(smokeGet('/'), 'HIT', 'php');
    });
});
