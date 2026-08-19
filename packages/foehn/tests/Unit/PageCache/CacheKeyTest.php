<?php

declare(strict_types=1);

use Studiometa\Foehn\PageCache\CacheKey;

describe('CacheKey', function () {
    it('maps the site root to one file', function () {
        $key = CacheKey::create('example.com', '/');

        expect($key?->relativePath())->toBe('example.com/index.html');
    });

    it('maps a path with and without its trailing slash to the same file', function () {
        expect(CacheKey::create('example.com', '/blog')?->relativePath())
            ->toBe(CacheKey::create('example.com', '/blog/')?->relativePath())
            ->toBe('example.com/blog/index.html');
    });

    it('decodes an accented permalink to the same file nginx would look for', function () {
        // nginx's $uri is decoded, so the three readers only agree if PHP decodes too.
        // French slugs make this load-bearing rather than theoretical.
        expect(CacheKey::create('example.com', '/%C3%A0-propos/')?->relativePath())
            ->toBe(CacheKey::create('example.com', '/à-propos/')?->relativePath())
            ->toBe('example.com/à-propos/index.html');
    });

    it('drops the query string, which is never part of the key', function () {
        expect(CacheKey::create('example.com', '/blog/?utm_source=x')?->path)->toBe('/blog');
    });

    it('collapses repeated slashes', function () {
        expect(CacheKey::create('example.com', '//blog///posts//')?->path)->toBe('/blog/posts');
    });

    it('refuses a traversal', function (string $uri) {
        expect(CacheKey::create('example.com', $uri))->toBeNull();
    })->with([
        ['/../../etc/passwd'],
        ['/blog/../../../etc/passwd'],
        ['/%2e%2e/%2e%2e/etc/passwd'],
        ['/blog/..'],
        ['/..'],
    ]);

    it('collapses the two spellings WordPress and a browser disagree on', function () {
        // WordPress stores a non-ASCII slug with lowercase percent escapes —
        // `utf8_uri_encode()` builds them with `dechex()` — while a browser sends
        // uppercase ones. So `get_permalink()` hands the purger a different spelling of
        // the URL than the recorder was asked for, and only decoding collapses the two
        // onto one file. This is the bug that left every accented archive stale.
        expect(CacheKey::create('example.com', '/%c3%a0-propos/')?->relativePath())
            ->toBe(CacheKey::create('example.com', '/%C3%A0-propos/')?->relativePath());
    });

    it('refuses a value that was encoded twice', function () {
        // Decoded once, on purpose: `%252e%252e` would become `%2e%2e` here and `..`
        // in whichever reader decoded again. There is no reading of it all four share.
        expect(CacheKey::create('example.com', '/%252e%252e/passwd'))->toBeNull();
        expect(CacheKey::create('example.com', '/100%25-cotton/'))->toBeNull();
    });

    it('refuses anything outside the allowlist, rather than sanitizing it', function (string $uri) {
        // Never rewrite a bad path into a good one. `/x/:8080/evil` is a real probe,
        // and a cache that turns a probe into a valid filename has agreed to store the
        // attacker's page.
        expect(CacheKey::create('example.com', $uri))->toBeNull();
    })->with([
        ['/x/:8080/evil'],
        ['/blog\\posts/'],
        ['/<script>alert(1)</script>/'],
        ['/hello world/'],
        ['/it%27s/'],
        ["/it's/"],
        ['/say-"what"/'],
        ["/blog\x00.html"],
        ["/blog\n/"],
        ["/blog/\n"],
        ['/%00/'],
        ['/a|b/'],
    ]);

    it('keeps the unreserved characters a real slug uses', function (string $uri) {
        expect(CacheKey::create('example.com', $uri))->not->toBeNull();
    })->with([
        ['/hello-world/'],
        ['/hello_world/'],
        ['/v1.2/'],
        ['/~user/'],
        ['/à-propos/'],
        ['/Ұlytau-oblysy/'],
        ['/日本語/'],
    ]);

    it('does not fold case, because nginx cannot', function () {
        // `$uri` preserves case and no map can lowercase it, so lowercasing here would
        // be a permanent miss on the fast path. Two entries is wasteful and correct.
        expect(CacheKey::create('example.com', '/Blog/')?->relativePath())->toBe('example.com/Blog/index.html');
        expect(CacheKey::create('example.com', '/blog/')?->relativePath())->toBe('example.com/blog/index.html');
    });

    it('treats the front controller as the home page rather than a second URL for it', function () {
        expect(CacheKey::create('example.com', '/index.php')?->relativePath())->toBe('example.com/index.html');
    });

    it('accepts only the filenames this cache writes', function () {
        expect(CacheKey::isWritableFilename('index.html'))->toBeTrue();
        expect(CacheKey::isWritableFilename('index__lang=fr.html'))->toBeTrue();
        expect(CacheKey::isWritableFilename('index.php'))->toBeFalse();
        expect(CacheKey::isWritableFilename('../index.html'))->toBeFalse();
        expect(CacheKey::isWritableFilename('index.html.tmp'))->toBeFalse();
        expect(CacheKey::isWritableFilename('shell.html'))->toBeFalse();
    });

    it('keys the three path shapes that cause mismatches', function () {
        expect(CacheKey::create('example.com', '/a//b/')?->relativePath())->toBe('example.com/a/b/index.html');
        expect(CacheKey::create('example.com', '/a/b')?->relativePath())->toBe('example.com/a/b/index.html');
        expect(CacheKey::create('example.com', '/')?->relativePath())->toBe('example.com/index.html');
    });

    it('refuses bytes that are not UTF-8', function () {
        // Written to disk they would be a filename nginx's decoded $uri never
        // produces: the file would exist and never be found.
        expect(CacheKey::create('example.com', '/' . rawurlencode("\xC3\x28") . '/'))->toBeNull();
    });

    it('refuses an over-long segment', function () {
        expect(CacheKey::create('example.com', '/' . str_repeat('a', 200) . '/'))->not->toBeNull();
        expect(CacheKey::create('example.com', '/' . str_repeat('a', 201) . '/'))->toBeNull();
    });

    it('refuses an over-long path', function () {
        $segments = array_fill(0, 10, str_repeat('a', 60));

        expect(CacheKey::create('example.com', '/' . implode('/', $segments) . '/'))->toBeNull();
    });

    it('lowercases the host and drops its port', function () {
        expect(CacheKey::create('Example.COM:8443', '/')?->host)->toBe('example.com');
    });

    it('refuses a host that is not one', function (string $host) {
        expect(CacheKey::create($host, '/'))->toBeNull();
    })->with([
        [''],
        ['exam ple.com'],
        ['example.com/../evil'],
        ['../evil'],
        ['exa..mple.com'],
        ["example.com\x00"],
    ]);

    it('reads the site host off WP_HOME, which is what the request host is checked against', function () {
        // WP_HOME is defined by the generated wp-config.php, which is what lets the
        // drop-in validate a Host header before WordPress exists.
        expect(CacheKey::siteHost())->toBe('example.com');
    });

    it('keeps the variant slot the filename reserves', function () {
        expect(CacheKey::FILENAME)->toBe('index.html');
    });
});
