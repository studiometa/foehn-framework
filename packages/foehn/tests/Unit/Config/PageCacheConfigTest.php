<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
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

    it('reserves section selection from ignored and keyed query configuration', function () {
        $config = new PageCacheConfig(ignoredQueryArgs: [SectionRequest::PARAMETER, 'utm_source'], cacheQueryArgs: [
            SectionRequest::PARAMETER,
            'page',
        ]);

        expect(PageCacheConfig::RESERVED_QUERY_ARGS)->toContain(SectionRequest::PARAMETER);
        expect($config->getIgnoredQueryArgs())->toBe(['utm_source']);
        expect($config->getCacheQueryArgs())->toHaveKeys(['page'])->not->toHaveKey(SectionRequest::PARAMETER);
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

    it('keeps 404s out of the cache until a project opts in', function () {
        expect(new PageCacheConfig()->cacheNotFound)->toBeFalse();
    });

    it('keeps a purge instant, by not letting the browser hold the page', function () {
        expect(new PageCacheConfig()->browserMaxAge)->toBe(0);
    });
});
