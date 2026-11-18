<?php

declare(strict_types=1);

use Studiometa\Foehn\Integrations\RankMath\RankMath;

beforeEach(function () {
    wp_stub_reset();
});

describe('RankMath', function () {
    it('reads the modules the site turned on', function () {
        $GLOBALS['rank_math_stub_modules'] = ['redirections'];

        expect(RankMath::isModuleActive('redirections'))->toBeTrue();
        expect(RankMath::isModuleActive('sitemap'))->toBeFalse();
    });

    it('gives the URL of the sitemap index when the sitemap module is on', function () {
        $GLOBALS['rank_math_stub_modules'] = ['sitemap'];

        expect(RankMath::sitemapUrl())->toBe('http://example.com/sitemap_index.xml');
    });

    it('prints JSON-LD only when its Schema module is on', function () {
        expect(RankMath::printsJsonLd())->toBeFalse();

        $GLOBALS['rank_math_stub_modules'] = ['rich-snippet'];

        expect(RankMath::printsJsonLd())->toBeTrue();
    });

    it('gives no sitemap URL when the sitemap module is off', function () {
        expect(RankMath::sitemapUrl())->toBeNull();
    });

    it('gives no breadcrumbs when the breadcrumbs of Rank Math are off', function () {
        expect(RankMath::breadcrumbs())->toBe([]);
    });

    it('gives the breadcrumbs as labels and URLs, the current page without a URL', function () {
        $GLOBALS['rank_math_stub_crumbs'] = [
            ['Home', 'http://example.com', 'hide_in_schema' => false],
            ['News &amp; views', 'http://example.com/news/', 'hide_in_schema' => false],
            ['Rock &#8217;n&#8217; roll', 'http://example.com/news/rock/', 'hide_in_schema' => false],
        ];

        expect(RankMath::breadcrumbs())->toBe([
            ['label' => 'Home', 'url' => 'http://example.com'],
            ['label' => 'News & views', 'url' => 'http://example.com/news/'],
            ['label' => 'Rock ’n’ roll', 'url' => null],
        ]);
    });

    it('gives an item with no link without a URL', function () {
        $GLOBALS['rank_math_stub_crumbs'] = [
            ['Home',   'http://example.com'],
            ['2026',   ''],
            ['Search', ''],
        ];

        expect(array_column(RankMath::breadcrumbs(), 'url'))->toBe(['http://example.com', null, null]);
    });

    it('drops the current post when Rank Math removes the post title', function () {
        $GLOBALS['wp_stub_template'] = 'single';
        $GLOBALS['rank_math_stub_settings'] = ['general.breadcrumbs_remove_post_title' => true];
        $GLOBALS['rank_math_stub_crumbs'] = [
            ['Home',  'http://example.com'],
            ['News',  'http://example.com/news/'],
            ['Hello', 'http://example.com/news/hello/'],
        ];

        expect(RankMath::breadcrumbs())->toBe([
            ['label' => 'Home', 'url' => 'http://example.com'],
            ['label' => 'News', 'url' => 'http://example.com/news/'],
        ]);
    });
});
