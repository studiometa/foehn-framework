<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsJob;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\PageCache\Warmer;
use Studiometa\Foehn\PageCache\WarmUrl;

describe('Warmer', function () {
    beforeEach(function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_environment_type'] = 'production';
        $this->config = new PageCacheConfig(enabled: true, path: pageCacheRoot());
    });

    it('is a job, so a site with two thousand pages does not warm them in one process', function () {
        $attributes = new ReflectionClass(Warmer::class)->getAttributes(AsJob::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->group)->toBe('foehn-page-cache');
    });

    it('requests a URL with no cookies, so the eligibility rules do not bypass it', function () {
        new Warmer($this->config)->warm('https://example.com/blog/');

        $call = wp_stub_get_calls('wp_remote_get')[0]['args'];

        expect($call['url'])->toBe('https://example.com/blog/');
        expect($call['args']['cookies'])->toBe([]);
        expect($call['args']['headers'])->toBe(['X-Foehn-Warm' => '1']);
    });

    it('does not follow a redirect, which would warm a URL nobody asked for', function () {
        new Warmer($this->config)->warm('https://example.com/blog/');

        expect(wp_stub_get_calls('wp_remote_get')[0]['args']['args']['redirection'])->toBe(0);
    });

    it('hands back the status it got', function () {
        $GLOBALS['wp_stub_remote_status'] = 200;

        expect(new Warmer($this->config)->warm('https://example.com/'))->toBe(200);
    });

    it('hands back nothing when the request failed', function () {
        $GLOBALS['wp_stub_remote_error'] = true;

        expect(new Warmer($this->config)->warm('https://example.com/'))->toBeNull();
    });

    it('requests nothing at all while the cache is off', function () {
        $off = new PageCacheConfig(enabled: false);

        expect(new Warmer($off)->warm('https://example.com/'))->toBeNull();
        expect(wp_stub_get_calls('wp_remote_get'))->toBe([]);
    });

    it('requests nothing in an environment the cache is inert in', function () {
        $GLOBALS['wp_stub_environment_type'] = 'local';

        expect(new Warmer($this->config)->warm('https://example.com/'))->toBeNull();
        expect(wp_stub_get_calls('wp_remote_get'))->toBe([]);
    });

    it('warms the URL a job carries', function () {
        (new Warmer($this->config))(new WarmUrl('https://example.com/journal/'));

        expect(wp_stub_get_calls('wp_remote_get')[0]['args']['url'])->toBe('https://example.com/journal/');
    });

    it('takes its list from the sitemap the site already publishes for crawlers', function () {
        // Rather than a second notion of "important pages" to keep in step with the one
        // search engines are given.
        $GLOBALS['wp_stub_sitemap_urls'] = [
            ['loc' => 'http://example.com/'],
            ['loc' => 'http://example.com/blog/'],
            ['loc' => 'http://example.com/about/'],
        ];

        expect(new Warmer($this->config)->urls())->toBe([
            'http://example.com/',
            'http://example.com/blog/',
            'http://example.com/about/',
        ]);
    });

    it('warms the home page even when the sitemap is switched off', function () {
        $GLOBALS['wp_stub_sitemap_providers'] = [];

        expect(new Warmer($this->config)->urls())->toBe(['http://example.com/']);
    });
});
