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

    it('gives no sitemap URL when the sitemap module is off', function () {
        expect(RankMath::sitemapUrl())->toBeNull();
    });
});
