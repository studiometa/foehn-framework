<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\BypassReason;

/**
 * Every row of the eligibility table, because this is the part that decides whether
 * the feature is safe. The failure mode of a page cache is not slowness — it is
 * serving the wrong HTML to the wrong visitor, and each rule below is one way of it.
 */

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_environment_type'] = 'production';
});

describe('Bypass: the request', function () {
    it('lets an ordinary anonymous GET through', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer()))->toBeNull();
    });

    it('refuses a cache nobody asked for', function () {
        expect(pageCacheBypass(new PageCacheConfig())->forRequest(pageCacheServer()))->toBe(BypassReason::Disabled);
    });

    it('refuses an environment the config did not name', function () {
        $GLOBALS['wp_stub_environment_type'] = 'local';

        expect(pageCacheBypass()->forRequest(pageCacheServer()))->toBe(BypassReason::Environment);
    });

    it('refuses anything but GET', function (string $method) {
        expect(pageCacheBypass()->forRequest(pageCacheServer(['REQUEST_METHOD' => $method])))
            ->toBe(BypassReason::Method);
    })->with([['POST'], ['PUT'], ['HEAD'], ['DELETE']]);

    it('refuses a GET carrying a body', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer(), [], ['comment' => 'hi']))->toBe(BypassReason::PostData);
    });

    it('refuses a host that is not the site', function () {
        // A cache path built from an unchecked Host header is a poisoning primitive:
        // one request writes the file a later request for the real host reads.
        expect(pageCacheBypass()->forRequest(pageCacheServer(['HTTP_HOST' => 'evil.test'])))->toBe(BypassReason::Host);
    });

    it('accepts the site host with a port on it', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer(['HTTP_HOST' => 'example.com:8443'])))->toBeNull();
    });

    it('refuses a path it will not key', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer(['REQUEST_URI' => '/../../etc/passwd'])))
            ->toBe(BypassReason::Path);
    });

    it('refuses a query string it cannot ignore', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer(['REQUEST_URI' => '/blog/?foo=bar'])))
            ->toBe(BypassReason::QueryString);
    });

    it('refuses section selection before a cached page can be served or recorded', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer([
            'REQUEST_URI' => '/blog/?sections=results',
        ])))
            ->toBe(BypassReason::QueryString);
    });

    it('lets a request through whose query is only tracking parameters', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer([
            'REQUEST_URI' => '/blog/?utm_source=newsletter',
        ])))->toBeNull();
    });

    it('refuses a bypass cookie by prefix', function (string $cookie) {
        expect(pageCacheBypass()->forRequest(pageCacheServer(), [$cookie => 'x']))->toBe(BypassReason::Cookie);
    })->with([
        ['wordpress_logged_in_abc123'],
        ['comment_author_abc123'],
        ['wp-postpass_abc123'],
    ]);

    it('ignores a cookie nobody named', function () {
        expect(pageCacheBypass()->forRequest(pageCacheServer(), ['_ga' => 'GA1.2.3']))->toBeNull();
    });

    it('refuses an excluded path, and the pages under it', function () {
        $bypass = pageCacheBypass(new PageCacheConfig(enabled: true, excludedPaths: ['/contact/']));

        expect($bypass->forRequest(pageCacheServer(['REQUEST_URI' => '/contact/'])))->toBe(BypassReason::ExcludedPath);
        expect($bypass->forRequest(pageCacheServer(['REQUEST_URI' => '/contact'])))->toBe(BypassReason::ExcludedPath);
        expect($bypass->forRequest(pageCacheServer(['REQUEST_URI' => '/contact/thanks/'])))
            ->toBe(BypassReason::ExcludedPath);
        expect($bypass->forRequest(pageCacheServer(['REQUEST_URI' => '/contacts/'])))->toBeNull();
    });

    it('refuses a site that is mid-update', function () {
        $root = sys_get_temp_dir() . '/foehn-tests/maintenance-' . uniqid('', true);
        mkdir($root, 0o777, true);
        touch($root . '/.maintenance');

        try {
            expect(pageCacheBypass()->forRequest(pageCacheServer(['DOCUMENT_ROOT' => $root])))
                ->toBe(BypassReason::Maintenance);
        } finally {
            unlink($root . '/.maintenance');
            rmdir($root);
        }
    });
});

describe('Bypass: the query string', function () {
    it('keys every ignored arg as no query at all, in any order they arrive in', function () {
        $bypass = pageCacheBypass();

        // Order-independence is not a nicety: the generated nginx and Apache snippets
        // test the same set with the same order-independence, and a reader that
        // disagreed would read a different file than the writer wrote.
        expect($bypass->canonicalQuery('/?utm_source=a&utm_medium=b&gclid=c'))->toBe('');
        expect($bypass->canonicalQuery('/?gclid=c&utm_medium=b&utm_source=a'))->toBe('');
    });

    it('bypasses on what it was not told about', function () {
        expect(pageCacheBypass()->canonicalQuery('/?utm_source=a&s=hello'))->toBeNull();
    });

    it('does not mistake a longer name for one it ignores', function () {
        // `utm_sourcex` is not `utm_source`, and treating it as one would serve the
        // no-query page for a URL that meant something else.
        expect(pageCacheBypass()->canonicalQuery('/?utm_sourcex=a'))->toBeNull();
    });

    it('treats a bare flag as the arg it names', function () {
        expect(pageCacheBypass()->canonicalQuery('/?utm_source'))->toBe('');
        expect(pageCacheBypass()->canonicalQuery('/?preview'))->toBeNull();
    });

    it('has no query to speak of when there is none', function () {
        expect(pageCacheBypass()->canonicalQuery('/blog/'))->toBe('');
        expect(pageCacheBypass()->canonicalQuery('/blog/?'))->toBe('');
    });

    it('keys a configured arg into the filename rather than bypassing on it', function () {
        // See QueryKeyTest for the canonical-order rules themselves; this is the seam
        // between them and the key the writer and the drop-in both use.
        $bypass = pageCacheBypass(new PageCacheConfig(enabled: true, cacheQueryArgs: ['page' => '^[0-9]{1,6}$']));

        $server = pageCacheServer(['REQUEST_URI' => '/blog/?page=2']);

        expect($bypass->key($server)?->relativePath())->toBe('example.com/blog/index__page=2&.html');
        expect($bypass->forRequest($server))->toBeNull();
    });
});

describe('Bypass: the context', function () {
    it('lets an ordinary page through', function () {
        expect(pageCacheBypass()->forContext(pageCacheServer()))->toBeNull();
    });

    it('refuses a request WordPress says is not a page', function (string $conditional, BypassReason $reason) {
        wp_stub_set_conditional($conditional, true);

        expect(pageCacheBypass()->forContext(pageCacheServer()))->toBe($reason);
    })->with([
        ['wp_doing_ajax',          BypassReason::Ajax],
        ['wp_doing_cron',          BypassReason::Cron],
        ['is_feed',                BypassReason::Feed],
        ['is_trackback',           BypassReason::Trackback],
        ['is_robots',              BypassReason::Robots],
        ['is_embed',               BypassReason::Embed],
        ['is_preview',             BypassReason::Preview],
        ['is_customize_preview',   BypassReason::CustomizePreview],
        ['post_password_required', BypassReason::PasswordRequired],
    ]);

    it('refuses the admin', function () {
        $GLOBALS['wp_stub_is_admin'] = true;

        expect(pageCacheBypass()->forContext(pageCacheServer()))->toBe(BypassReason::Admin);
    });

    it('refuses a search results page', function () {
        // A search page is one URL per query, so caching it fills the disk with
        // single-use files — and `?s=` is a query string this cache bypasses anyway.
        $GLOBALS['wp_stub_template'] = 'search';

        expect(pageCacheBypass()->forContext(pageCacheServer()))->toBe(BypassReason::Search);
    });

    it('refuses a logged-in visitor even with no cookie the config knows', function () {
        $GLOBALS['wp_stub_logged_in'] = true;

        expect(pageCacheBypass()->forContext(pageCacheServer()))->toBe(BypassReason::LoggedIn);
    });
});

describe('Bypass: the response', function () {
    it('lets a complete 200 of HTML through', function () {
        expect(pageCacheBypass()->forResponse(
            pageCacheBody(),
            200,
            ['Content-Type: text/html; charset=UTF-8'],
            pageCacheServer(),
        ))->toBeNull();
    });

    it('assumes HTML when nothing said otherwise', function () {
        expect(pageCacheBypass()->forResponse(pageCacheBody(), 200, [], pageCacheServer()))->toBeNull();
    });

    it('refuses anything but a 200', function (int $status) {
        expect(pageCacheBypass()->forResponse(pageCacheBody(), $status, [], pageCacheServer()))
            ->toBe(BypassReason::Status);
    })->with([[301], [302], [404], [500], [503]]);

    it('caches a 404 only when asked to', function () {
        $config = new PageCacheConfig(enabled: true, cacheNotFound: true);

        expect(pageCacheBypass($config)->forResponse(pageCacheBody(), 404, [], pageCacheServer()))->toBeNull();
    });

    it('refuses a response that is not HTML', function () {
        expect(pageCacheBypass()->forResponse(
            pageCacheBody(),
            200,
            ['Content-Type: application/json'],
            pageCacheServer(),
        ))
            ->toBe(BypassReason::ContentType);
    });

    it('refuses a redirect', function () {
        expect(pageCacheBypass()->forResponse(pageCacheBody(), 200, ['Location: /elsewhere/'], pageCacheServer()))
            ->toBe(BypassReason::Redirect);
    });

    it('refuses a body too short to be a page', function () {
        expect(pageCacheBypass()->forResponse('<html></html>', 200, [], pageCacheServer()))
            ->toBe(BypassReason::BodyTooShort);
    });

    it('refuses a render that died before it finished', function () {
        // A fatal mid-template still flushes its buffer. That is exactly the page that
        // must not be frozen for the next eight hours.
        $truncated = '<html><body>' . str_repeat('x', 300) . '<p>Fatal error: ';

        expect(pageCacheBypass()->forResponse($truncated, 200, [], pageCacheServer()))
            ->toBe(BypassReason::BodyIncomplete);
    });

    it('tolerates whitespace after the closing tag', function () {
        expect(pageCacheBypass()->forResponse(pageCacheBody() . "\n\n", 200, [], pageCacheServer()))->toBeNull();
    });

    it('refuses a body holding a substring the config excluded', function () {
        // The documented opt-out for nonce-bearing pages: a nonce frozen in a cached
        // page expires with its window, and the form then fails until a re-render.
        $config = new PageCacheConfig(enabled: true, excludeWhenBodyContains: ['name="_wpnonce"']);
        $body = pageCacheBody('<input type="hidden" name="_wpnonce" value="ab12">');

        expect(pageCacheBypass($config)->forResponse($body, 200, [], pageCacheServer()))
            ->toBe(BypassReason::BodyExcluded);
    });

    it('still applies every request rule at flush time', function () {
        expect(pageCacheBypass()->forResponse(pageCacheBody(), 200, [], pageCacheServer([
            'HTTP_HOST' => 'evil.test',
        ])))->toBe(BypassReason::Host);
    });
});

describe('Bypass: the key', function () {
    it('hands back the key a valid request maps to', function () {
        expect(pageCacheBypass()->key(pageCacheServer())?->relativePath())->toBe('example.com/blog/index.html');
    });

    it('hands back nothing for a host that is not the site', function () {
        expect(pageCacheBypass()->key(pageCacheServer(['HTTP_HOST' => 'evil.test'])))->toBeNull();
    });
});
