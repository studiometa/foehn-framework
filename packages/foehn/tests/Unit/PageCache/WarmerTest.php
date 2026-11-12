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
        pageCacheWarmer($this->config)->warm('https://example.com/blog/');

        $call = wp_stub_get_calls('wp_remote_get')[0]['args'];

        expect($call['url'])->toBe('https://example.com/blog/');
        expect($call['args']['cookies'])->toBe([]);
        expect($call['args']['headers'])->toBe(['X-Foehn-Warm' => '1']);
    });

    it('does not follow a redirect, which would warm a URL nobody asked for', function () {
        pageCacheWarmer($this->config)->warm('https://example.com/blog/');

        expect(wp_stub_get_calls('wp_remote_get')[0]['args']['args']['redirection'])->toBe(0);
    });

    it('hands back the status it got', function () {
        $GLOBALS['wp_stub_remote_status'] = 200;

        expect(pageCacheWarmer($this->config)->warm('https://example.com/'))->toBe(200);
    });

    it('hands back nothing when the request failed', function () {
        $GLOBALS['wp_stub_remote_error'] = true;

        expect(pageCacheWarmer($this->config)->warm('https://example.com/'))->toBeNull();
    });

    it('requests nothing at all while the cache is off', function () {
        $off = new PageCacheConfig(enabled: false);

        expect(pageCacheWarmer($off)->warm('https://example.com/'))->toBeNull();
        expect(wp_stub_get_calls('wp_remote_get'))->toBe([]);
    });

    it('requests nothing in an environment the cache is inert in', function () {
        $GLOBALS['wp_stub_environment_type'] = 'local';

        expect(pageCacheWarmer($this->config)->warm('https://example.com/'))->toBeNull();
        expect(wp_stub_get_calls('wp_remote_get'))->toBe([]);
    });

    it('warms the URL a job carries', function () {
        pageCacheWarmer($this->config)(new WarmUrl('https://example.com/journal/'));

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

        expect(pageCacheWarmer($this->config)->urls())->toBe([
            'http://example.com/',
            'http://example.com/blog/',
            'http://example.com/about/',
        ]);
    });

    it('warms the home page even when the sitemap is switched off', function () {
        $GLOBALS['wp_stub_sitemap_providers'] = [];

        expect(pageCacheWarmer($this->config)->urls())->toBe(['http://example.com/']);
    });

    it('drops a URL that matches a warmExclude pattern, anywhere in the full URL', function () {
        $config = new PageCacheConfig(enabled: true, path: pageCacheRoot(), warmExclude: ['/test-form/', '\\?lang=']);
        $GLOBALS['wp_stub_sitemap_urls'] = [
            ['loc' => 'http://example.com/test-form/'],
            ['loc' => 'http://example.com/fr/test-form/step-2/'],
            ['loc' => 'http://example.com/blog/'],
        ];

        expect(pageCacheWarmer($config)->urls())->toBe(['http://example.com/', 'http://example.com/blog/']);
    });

    it('drops a URL the cache would never store', function () {
        // Another host, a query string nobody keyed, a path under `excludedPaths`: a warm
        // request for one of them costs a render and fills nothing.
        $config = new PageCacheConfig(enabled: true, path: pageCacheRoot(), excludedPaths: ['/account/']);

        expect(pageCacheWarmer($config)->warmable([
            'http://example.com/blog/',
            'http://elsewhere.example/blog/',
            'http://example.com/blog/?color=red',
            'http://example.com/account/orders/',
        ]))->toBe(['http://example.com/blog/']);
    });

    it('lists each URL once', function () {
        expect(pageCacheWarmer($this->config)->warmable([
            'http://example.com/blog/',
            'http://example.com/blog/',
        ]))->toBe(['http://example.com/blog/']);
    });

    it('ignores a warmExclude pattern that does not compile', function () {
        $config = new PageCacheConfig(enabled: true, path: pageCacheRoot(), warmExclude: ['(', 'a#b']);

        expect(pageCacheWarmer($config)->warmable(['http://example.com/a#b/']))->toBe(['http://example.com/a#b/']);
    });

    it('reads the sitemap that warmSitemap names, following its index', function () {
        // Rank Math and Yoast switch the core sitemap off and publish an index instead.
        $config = new PageCacheConfig(
            enabled: true,
            path: pageCacheRoot(),
            warmSitemap: 'http://example.com/sitemap_index.xml',
        );
        $GLOBALS['wp_stub_remote_bodies'] = [
            'http://example.com/sitemap_index.xml' => <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <sitemap><loc>http://example.com/post-sitemap.xml</loc></sitemap>
                    <sitemap><loc>http://example.com/page-sitemap.xml</loc></sitemap>
                    <sitemap><loc>http://example.com/missing-sitemap.xml</loc></sitemap>
                </sitemapindex>
                XML,
            'http://example.com/post-sitemap.xml' => <<<'XML'
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url>
                        <loc>http://example.com/hello-world/</loc>
                        <image:image><image:loc>http://example.com/hello.jpg</image:loc></image:image>
                    </url>
                    <url><loc> http://example.com/journal/?page_id=4&amp;x=1 </loc></url>
                </urlset>
                XML,
            'http://example.com/page-sitemap.xml' => <<<'XML'
                <urlset><url><loc>http://example.com/about/</loc></url></urlset>
                XML,
        ];

        expect(pageCacheWarmer($config)->urls())->toBe([
            'http://example.com/',
            'http://example.com/hello-world/',
            'http://example.com/about/',
        ]);
        expect(array_column(wp_stub_get_calls('wp_remote_get'), 'args'))->toHaveCount(4);
    });

    it('decodes the XML entities of a location', function () {
        $config = new PageCacheConfig(
            enabled: true,
            path: pageCacheRoot(),
            cacheQueryArgs: ['lang', 'page'],
            warmSitemap: 'http://example.com/sitemap.xml',
        );
        $GLOBALS['wp_stub_remote_bodies'] = [
            'http://example.com/sitemap.xml' => '<urlset><url><loc>http://example.com/?lang=fr&amp;page=2</loc></url></urlset>',
        ];

        expect(pageCacheWarmer($config)->urls())->toBe(['http://example.com/', 'http://example.com/?lang=fr&page=2']);
    });

    it('stops following sitemap indexes two levels down, so a loop ends', function () {
        $config = new PageCacheConfig(enabled: true, path: pageCacheRoot(), warmSitemap: 'http://example.com/a.xml');
        $index = static fn(string $next): string => (
            '<sitemapindex><sitemap><loc>' . $next . '</loc></sitemap></sitemapindex>'
        );
        $GLOBALS['wp_stub_remote_bodies'] = [
            'http://example.com/a.xml' => $index('http://example.com/b.xml'),
            'http://example.com/b.xml' => $index('http://example.com/c.xml'),
            'http://example.com/c.xml' => $index('http://example.com/d.xml'),
            'http://example.com/d.xml' => '<urlset><url><loc>http://example.com/deep/</loc></url></urlset>',
        ];

        expect(pageCacheWarmer($config)->urls())->toBe(['http://example.com/']);
        expect(wp_stub_get_calls('wp_remote_get'))->toHaveCount(3);
    });

    it('reads no core sitemap while warmSitemap names another', function () {
        $config = new PageCacheConfig(
            enabled: true,
            path: pageCacheRoot(),
            warmSitemap: 'http://example.com/sitemap.xml',
        );
        $GLOBALS['wp_stub_sitemap_urls'] = [['loc' => 'http://example.com/core-only/']];
        $GLOBALS['wp_stub_remote_bodies'] = [];

        expect(pageCacheWarmer($config)->urls())->toBe(['http://example.com/']);
    });
});
