<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\QueryKey;
use Studiometa\Foehn\Views\Sections\SectionRequest;

describe('PageCacheConfig', function () {
    beforeEach(function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_environment_type'] = 'production';
    });

    it('is off until a project asks for it', function () {
        // A cache nobody asked for is a bug, not a performance win.
        expect(new PageCacheConfig()->enabled)->toBeFalse();
    });

    it('allows production alone by default', function () {
        expect(new PageCacheConfig()->environments)->toBe(['production']);
    });

    it('stores under wp-content, next to the discovery cache', function () {
        expect(new PageCacheConfig()->getPath())->toBe(WP_CONTENT_DIR . '/cache/foehn/pages');
    });

    it('takes a path a project chose, without its trailing slash', function () {
        expect(new PageCacheConfig(path: '/srv/cache/pages/')->getPath())->toBe('/srv/cache/pages');
    });

    it('bypasses the three cookies that mean "this visitor is not anonymous"', function () {
        expect(new PageCacheConfig()->bypassCookies)->toBe(['wordpress_logged_in_', 'comment_author_', 'wp-postpass_']);
    });

    it('ignores the tracking parameters that would otherwise split every page', function () {
        expect(new PageCacheConfig()->ignoredQueryArgs)
            ->toContain('utm_source')
            ->toContain('gclid')
            ->toContain('fbclid')
            ->toContain('mc_cid');
    });

    it('refuses to let a project ignore section selection', function () {
        // The rule that must not stop holding. An ignored `foehn_sections` would key a
        // section request onto the whole page's file: one visitor asks for a fragment and
        // is handed a page, the next asks for the page and is handed a fragment.
        $config = new PageCacheConfig(ignoredQueryArgs: [SectionRequest::PARAMETER, 'utm_source'], cacheQueryArgs: [
            'page',
        ]);

        expect(PageCacheConfig::RESERVED_QUERY_ARGS)->toContain(SectionRequest::PARAMETER);
        expect($config->getIgnoredQueryArgs())->toBe(['utm_source']);
    });

    it('keys section selection with the parser grammar, whatever the config says', function () {
        // On by default and not configurable: a fragment is a different response for the
        // same URL, so it needs a file of its own — and the grammar belongs to
        // SectionRequest, because a widened one would key values the parser refuses.
        $config = new PageCacheConfig(cacheQueryArgs: [SectionRequest::PARAMETER => '^.+$', 'page']);

        expect($config->getCacheQueryArgs())->toBe([
            SectionRequest::PARAMETER => SectionRequest::VALUE_PATTERN,
            'page' => PageCacheConfig::DEFAULT_QUERY_ARG_PATTERN,
        ]);
        expect(new PageCacheConfig()->getCacheQueryArgs())->toBe([
            SectionRequest::PARAMETER => SectionRequest::VALUE_PATTERN,
        ]);
    });

    it('keys neither of two names nginx would hold in one variable', function () {
        // `a-b` and `a_b` are both `$foehn_val_a_b` in the snippet, and the second `set`
        // would overwrite the first — a page keyed under the wrong filter. Dropping both
        // makes them a bypass in every reader, which is the safe direction.
        $config = new PageCacheConfig(cacheQueryArgs: ['a-b', 'a_b', 'page']);

        expect(array_keys(projectCacheQueryArgs($config)))->toBe(['page']);
        expect(QueryKey::canonical('a-b=x', $config))->toBeNull();
        expect(QueryKey::canonical('a_b=x', $config))->toBeNull();
    });

    it('keeps a hyphenated name that collides with nothing', function () {
        $config = new PageCacheConfig(cacheQueryArgs: ['product-type']);

        expect(array_keys(projectCacheQueryArgs($config)))->toBe(['product-type']);
        expect(QueryKey::canonical('product-type=shoes', $config))->toBe('product-type=shoes&');
    });

    it('never lets a project name displace the reserved one', function () {
        // `foehn-sections` would share `$foehn_val_foehn_sections` with the reserved arg;
        // the project's spelling is the one that goes.
        $config = new PageCacheConfig(cacheQueryArgs: ['foehn-sections']);

        expect($config->getCacheQueryArgs())->toBe([SectionRequest::PARAMETER => SectionRequest::VALUE_PATTERN]);
    });

    it('reads the environment off WordPress', function () {
        $GLOBALS['wp_stub_environment_type'] = 'staging';

        expect(PageCacheConfig::environment())->toBe('staging');
    });

    it('answers whether the environment it is in is one it was allowed in', function () {
        $config = new PageCacheConfig(enabled: true, environments: ['production', 'staging']);

        expect($config->allowsEnvironment('production'))->toBeTrue();
        expect($config->allowsEnvironment('staging'))->toBeTrue();
        expect($config->allowsEnvironment('local'))->toBeFalse();
    });

    it('follows WP_DEBUG for the debug headers unless told otherwise', function () {
        expect(new PageCacheConfig()->wantsDebugHeaders())->toBe(defined('WP_DEBUG') && (bool) constant('WP_DEBUG'));
        expect(new PageCacheConfig(debugHeaders: true)->wantsDebugHeaders())->toBeTrue();
        expect(new PageCacheConfig(debugHeaders: false)->wantsDebugHeaders())->toBeFalse();
    });

    it('warms nothing after a purge until a project opts in', function () {
        $config = new PageCacheConfig();

        expect($config->warmAfterPurge)->toBeFalse();
        expect($config->warmSitemap)->toBeNull();
        expect($config->warmExclude)->toBe([]);
    });

    it('matches a warmExclude pattern anywhere in the full URL', function () {
        $config = new PageCacheConfig(warmExclude: ['/test-form/', '^https://example\.com/shop/(.*)/print/$']);

        expect($config->excludesFromWarmup('https://example.com/test-form/'))->toBeTrue();
        expect($config->excludesFromWarmup('https://example.com/en/test-form/thanks/'))->toBeTrue();
        expect($config->excludesFromWarmup('https://example.com/shop/a-chair/print/'))->toBeTrue();
        expect($config->excludesFromWarmup('https://example.com/shop/a-chair/'))->toBeFalse();
    });

    it('drops a warmExclude pattern that cannot compile, without a warning', function () {
        expect(new PageCacheConfig(warmExclude: ['(', '#'])->excludesFromWarmup('https://example.com/(#'))->toBeFalse();
    });

    it('keeps 404s out of the cache until a project opts in', function () {
        expect(new PageCacheConfig()->cacheNotFound)->toBeFalse();
    });

    it('keeps a purge instant, by not letting the browser hold the page', function () {
        expect(new PageCacheConfig()->browserMaxAge)->toBe(0);
    });
});

describe('values instead of patterns', function () {
    it('keys exactly the values a project listed', function () {
        // The form to reach for: a project states the page sizes it supports, and the
        // pattern that means the same thing is compiled from them.
        $config = new PageCacheConfig(cacheQueryArgs: ['posts_per_page' => [12, 24, 48]]);

        expect(projectCacheQueryArgs($config))->toBe(['posts_per_page' => '^(?:12|24|48)$']);
        expect(QueryKey::canonical('posts_per_page=24', $config))->toBe('posts_per_page=24&');
        expect(QueryKey::canonical('posts_per_page=99', $config))->toBeNull();
    });

    it('quotes a listed value rather than letting it widen into a pattern', function () {
        // A config file lists values. `1.5` must match `1.5` and not `165`, or the cache
        // keys pages it was never told to key.
        $config = new PageCacheConfig(cacheQueryArgs: ['v' => ['1.5']]);

        expect(QueryKey::canonical('v=1.5', $config))->toBe('v=1.5&');
        expect(QueryKey::canonical('v=165', $config))->toBeNull();
    });

    it('lets an empty list match nothing at all', function () {
        // "These values are allowed" with none named is a bypass, not a free pass.
        $config = new PageCacheConfig(cacheQueryArgs: ['v' => []]);

        expect(QueryKey::canonical('v=anything', $config))->toBeNull();
    });

    it('still takes a bare name and a pattern', function () {
        $config = new PageCacheConfig(cacheQueryArgs: ['page', 'lang' => '^[a-z]{2}$']);

        expect(array_keys(projectCacheQueryArgs($config)))->toBe(['lang', 'page']);
        expect(QueryKey::canonical('page=2&lang=fr', $config))->toBe('lang=fr&page=2&');
    });
});

describe('the headers of a hit', function () {
    it('builds Cache-Control from browserMaxAge unless the project set one', function () {
        expect(new PageCacheConfig()->getCacheControl())->toBe('public, max-age=0, must-revalidate');
        expect(new PageCacheConfig(browserMaxAge: 60)->getCacheControl())->toBe('public, max-age=60, must-revalidate');
        expect(new PageCacheConfig(browserMaxAge: -5)->getCacheControl())->toBe('public, max-age=0, must-revalidate');
        expect(
            new PageCacheConfig(
                browserMaxAge: 60,
                cacheControl: 'no-cache, no-store, must-revalidate',
            )->getCacheControl(),
        )
            ->toBe('no-cache, no-store, must-revalidate');
    });

    it('sends no extra header by default', function () {
        expect(new PageCacheConfig()->getResponseHeaders())->toBe([]);
    });

    it('takes the headers a server sets at server level', function () {
        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'self'; img-src * data:",
        ];

        expect(new PageCacheConfig(responseHeaders: $headers)->getResponseHeaders())->toBe($headers);
    });

    it('refuses a Cache-Control value that could break out of the nginx string or the header', function (string $value) {
        expect(fn() => new PageCacheConfig(cacheControl: $value)->getCacheControl())
            ->toThrow(InvalidArgumentException::class, 'cacheControl');
    })->with([
        'a double quote' => ['no-cache"; add_header X-Evil "1'],
        'a line break' => ["no-cache\r\nX-Evil: 1"],
        'a trailing newline' => ["no-cache\n"],
        'an nginx variable' => ['max-age=$arg_x'],
        'a backslash' => ['no-cache\\'],
        'a non-ASCII character' => ['no-cache, max-âge=0'],
        'an empty value' => [''],
        'a leading space' => [' no-cache'],
    ]);

    it('refuses a header name nginx would have to quote, or a list instead of a map', function (array $headers) {
        expect(fn() => new PageCacheConfig(responseHeaders: $headers)->getResponseHeaders())
            ->toThrow(InvalidArgumentException::class, 'is not a header name');
    })->with([
        'a list of lines' => [['X-Frame-Options: SAMEORIGIN']],
        'a space' => [['X Frame' => 'SAMEORIGIN']],
        'a colon' => [['X-Frame-Options:' => 'SAMEORIGIN']],
        'a semicolon' => [['X-A;add_header' => 'b']],
        'a trailing newline' => [["X-A\n" => 'b']],
    ]);

    it('refuses a header value that could break out of the nginx string or the header', function (string $value) {
        expect(fn() => new PageCacheConfig(responseHeaders: ['X-Frame-Options' => $value])->getResponseHeaders())
            ->toThrow(InvalidArgumentException::class, 'responseHeaders: X-Frame-Options');
    })->with([
        'a double quote' => ['SAMEORIGIN"; add_header X-Evil "1'],
        'a line break' => ["SAMEORIGIN\r\nSet-Cookie: a=b"],
        'an nginx variable' => ['$http_host'],
        'an empty value' => [''],
    ]);

    it('refuses a header the cache sets itself', function (string $name) {
        expect(fn() => new PageCacheConfig(responseHeaders: [$name => 'x'])->getResponseHeaders())
            ->toThrow(InvalidArgumentException::class, 'the page cache sets');
    })->with(['Cache-Control', 'vary', 'ETag', 'Content-Type', 'Set-Cookie', 'X-Foehn-Cache', 'X-Robots-Tag']);

    it('points a Cache-Control header to cacheControl', function () {
        expect(fn() => new PageCacheConfig(responseHeaders: ['Cache-Control' => 'no-store'])->getResponseHeaders())
            ->toThrow(InvalidArgumentException::class, 'Use cacheControl.');
    });
});
