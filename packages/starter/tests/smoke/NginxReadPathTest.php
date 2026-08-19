<?php

declare(strict_types=1);

use Studiometa\Foehn\Smoke\Support\Site;

/**
 * The page cache served by nginx, which is the path the whole design is for: a stored
 * page goes out without PHP starting at all.
 *
 * The nginx include is installed once for this file, because reloading the server per
 * test would dominate the run. Everything here therefore expects `via: nginx` on a hit —
 * and that expectation is the point. A broken nginx snippet would otherwise hide behind
 * the PHP drop-in indefinitely, since both answer HIT.
 */

beforeAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::enableCache();
    Site::wp("wp rewrite structure '/%postname%/' --hard");
    Site::useNginx(true);
});

afterAll(function () {
    if (!Site::isRunning()) {
        return;
    }

    Site::disableCache();
    Site::clearCache();
});

beforeEach(function () {
    if (!Site::isRunning()) {
        $this->markTestSkipped('ddev is not running — start it in packages/starter and try again.');
    }

    Site::clearCache();
});

describe('nginx read path', function () {
    it('renders a page once, then serves it without starting PHP', function () {
        [$first, $second] = smokeGetTwice('/');

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'nginx');
        expectSameBody($first, $second, 'nginx served bytes that are not the ones PHP stored');
    });

    it('stores the page at the path every reader computes for it', function () {
        smokeWarm('/');

        expect(Site::cacheFile('/'))->toBeFile();
        expect(Site::cachedFiles())->toContain(Site::host() . '/index.html');
    });

    it('sends the headers a shared cache needs to not get this wrong', function () {
        // Vary: Cookie is what stops a proxy handing a cached anonymous page to a
        // logged-in visitor, which is the failure mode this feature exists to avoid.
        $response = smokeWarm('/');

        expect($response->header('vary'))->toContain('Cookie');
        expect($response->header('cache-control'))->toContain('must-revalidate');
    });

    it('never serves or stores a page for a visitor carrying a login cookie', function () {
        smokeWarm('/');
        $before = Site::cachedFiles();

        $response = smokeGet('/', ['wordpress_logged_in_smoke' => '1']);

        expectCache($response, 'BYPASS', 'php');
        expect($response->reason())->toBe('cookie');
        expect(Site::cachedFiles())->toBe($before, 'a logged-in request wrote to the cache');
    });

    it('bypasses every cookie prefix the config names', function (string $cookie, string $reason) {
        $response = smokeGet('/', [$cookie => '1']);

        expectCache($response, 'BYPASS', 'php');
        expect($response->reason())->toBe($reason);
    })->with([
        'a logged-in cookie' => ['wordpress_logged_in_abc123', 'cookie'],
        'a comment author cookie' => ['comment_author_abc123', 'cookie'],
        'a post password cookie' => ['wp-postpass_abc123', 'cookie'],
    ]);

    it('leaves a cookie nobody named alone', function () {
        smokeWarm('/');

        expectCache(smokeGet('/', ['_ga' => 'GA1.2.3']), 'HIT', 'nginx');
    });
});

describe('nginx read path: query strings', function () {
    beforeEach(function () {
        smokeWarm('/');
    });

    it('decides a query string the same way the writer does', function (string $query, string $state) {
        // The design turns on nginx and PHP agreeing about which requests map to which
        // file. These are the cases where they could disagree.
        expectCache(smokeGet('/' . $query), $state);
    })->with([
        'no query at all' => ['', 'HIT'],
        'an empty query' => ['?', 'HIT'],
        'one tracking arg' => ['?utm_source=x', 'HIT'],
        'two tracking args' => ['?utm_source=a&utm_medium=b', 'HIT'],
        'the same two, reversed' => ['?utm_medium=b&utm_source=a', 'HIT'],
        'a tracking arg with no value' => ['?utm_source=', 'HIT'],
        'a click id' => ['?gclid=abc', 'HIT'],
        // The query string never reaches a filename, so a traversal inside an ignored arg
        // has nothing to traverse. Worth pinning: the opposite would be a file write.
        'a traversal inside a tracking arg' => ['?utm_source=../../etc/passwd', 'HIT'],
        'an arg nobody ignores' => ['?foo=bar', 'BYPASS'],
        'a search' => ['?s=hello', 'BYPASS'],
        'a tracking arg alongside a real one' => ['?utm_source=x&foo=bar', 'BYPASS'],
        // `page` is not ignorable and must never be — ?page=2 is a different page from
        // ?page=1 — and this configuration does not key it either, so it is simply an arg
        // nobody configured. Configure it and both orders hit one file instead: see
        // NginxKeyedQueryTest.
        'pagination and a language' => ['?page=2&lang=fr', 'BYPASS'],
        'the same two, reversed' => ['?lang=fr&page=2', 'BYPASS'],
        'a repeated arg' => ['?page=1&page=2', 'BYPASS'],
        'an arg with no value' => ['?page=', 'BYPASS'],
        'a traversal in an arg nobody ignores' => ['?page=../x', 'BYPASS'],
        'a nearly-ignored arg' => ['?utm_sourcex=a', 'BYPASS'],
    ]);

    it('reads one file for two ignored args in either order', function (string $first, string $second) {
        // nginx has no way to sort a query string, so the two orders can only agree by
        // construction. Body identity is the proof: a HIT header would be satisfied by a
        // fresh render.
        $a = smokeGet('/' . $first);
        $b = smokeGet('/' . $second);

        expectCache($a, 'HIT', 'nginx');
        expectCache($b, 'HIT', 'nginx');
        expectSameBody($a, $b, 'the two argument orders were served different files');
    })->with([
        'tracking args' => ['?utm_source=a&utm_medium=b', '?utm_medium=b&utm_source=a'],
        'a click id and a campaign' => ['?gclid=1&utm_campaign=c', '?utm_campaign=c&gclid=1'],
    ]);

    it('reaches the same decision for two orders of a query it will not cache', function () {
        // The honest version of "same file, either order" for args that are not ignorable:
        // there is no file, and both orders say so for the same reason.
        $a = smokeGet('/?page=2&lang=fr');
        $b = smokeGet('/?lang=fr&page=2');

        expectCache($a, 'BYPASS', 'php');
        expectCache($b, 'BYPASS', 'php');
        expect($a->reason())->toBe($b->reason())->toBe('query-string');
        expectRendered($a, 'a bypassed request was served a stored page');
        expectRendered($b, 'a bypassed request was served a stored page');
    });

    it('renders a bypassed request rather than serving the cached one', function () {
        $cached = smokeGet('/');
        $bypassed = smokeGet('/?foo=bar');

        expectCache($cached, 'HIT', 'nginx');
        expectMarked($cached, 'the cached page lost the marker that identifies it');
        expectCache($bypassed, 'BYPASS', 'php');
        expectRendered($bypassed, 'the bypassed response is the cached page, which it must never be');
    });
});

describe('nginx read path: invalidation', function () {
    beforeEach(function () {
        // WordPress stores a non-ASCII slug with lowercase percent escapes —
        // utf8_uri_encode() builds them with dechex() — while a browser sends uppercase
        // ones. So the recorder and the purger are handed two spellings of one URL, and
        // this is where this class of feature usually breaks: wp-super-cache #1080/#1081
        // left every accented archive stale because the purge looked for a directory that
        // did not exist.
        $created = Site::wp('wp post create --post_title="Ұlytau oblysy" --post_status=publish --porcelain');

        expect($created)->toBeNumeric('could not create the non-ASCII post');

        $this->postId = (int) $created;

        // Derived, not hard-coded: a leftover post from an interrupted run holds the slug
        // and WordPress hands this one `…-2`, at which point every assertion here is about
        // a URL that belongs to a different post. That failure looks exactly like the bug
        // these tests are for, which is the worst way to waste an afternoon.
        $permalink = (string) Site::wp(sprintf('wp post url %d', $this->postId));
        $this->requested = (string) parse_url($permalink, PHP_URL_PATH);
        $this->path = rawurldecode($this->requested);

        // No message argument: toContain() is variadic, so a second string is read as
        // another needle rather than as an explanation.
        expect($this->path)->toContain('ұlytau-oblysy');
    });

    afterEach(function () {
        Site::wp(sprintf('wp post delete %d --force', $this->postId));
    });

    it('stores an accented permalink once, under its decoded name', function () {
        [$first, $second] = smokeGetTwice($this->requested);

        expectCache($first, 'MISS', 'php');
        expectCache($second, 'HIT', 'nginx');
        expect(Site::cacheFile($this->path))->toBeFile();
    });

    it('writes no percent-escaped directory beside the decoded one', function () {
        // Two spellings on disk means the readers are keying differently, and one of them
        // will never find what the other wrote.
        smokeWarm($this->requested);

        expect(array_filter(Site::cachedFiles(), static fn(string $file): bool => str_contains($file, '%')))
            ->toBe([], 'a percent-escaped path was written as well as the decoded one');
    });

    it('purges the accented page when the post is edited', function () {
        smokeWarm($this->requested);

        expect(Site::cacheFile($this->path))->toBeFile();

        Site::wp(sprintf('wp post update %d --post_content=%s', $this->postId, escapeshellarg('edited')));

        expect(Site::cacheFile($this->path))->not->toBeFile();
        expectCache(smokeGet($this->requested), 'MISS', 'php');
    });

    it('purges the front page too, because the post is listed on it', function () {
        smokeWarm('/');

        expect(Site::cacheFile('/'))->toBeFile();

        Site::wp(sprintf('wp post update %d --post_content=%s', $this->postId, escapeshellarg('edited again')));

        expect(Site::cacheFile('/'))->not->toBeFile();
    });

    it('empties the cache on `wp foehn cache:clear`', function () {
        smokeWarm('/');
        smokeWarm($this->requested);

        expect(Site::cachedFiles())->not->toBe([]);

        Site::clearCache();

        expect(Site::cachedFiles())->toBe([]);
    });
});
