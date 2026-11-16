<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Hooks\Security\DisableAuthorSitemap;

beforeEach(function () {
    wp_stub_reset();

    $this->hooks = new DisableAuthorSitemap();
});

describe('DisableAuthorSitemap', function () {
    it('does not register the users provider of the core sitemap', function () {
        $provider = new stdClass();

        expect($this->hooks->removeCoreProvider($provider, 'users'))->toBeFalse();
        expect($this->hooks->removeCoreProvider($provider, 'posts'))->toBe($provider);
    });

    it('turns off the author sitemap in the settings Rank Math has loaded, and keeps the others', function () {
        $GLOBALS['rank_math_stub_settings'] = ['sitemap.authors_sitemap' => true, 'general.breadcrumbs' => true];

        $this->hooks->turnOffRankMathSitemap();

        expect($GLOBALS['rank_math_stub_loaded'])->toBe([
            'sitemap.authors_sitemap' => false,
            'general.breadcrumbs' => true,
        ]);
    });

    it('turns it off after Rank Math loads its settings again on init', function () {
        $attribute = new ReflectionMethod(
            DisableAuthorSitemap::class,
            'turnOffRankMathSitemap',
        )->getAttributes(AsAction::class)[0]->newInstance();

        expect($attribute->hook)->toBe('init');
        expect($attribute->priority)->toBeGreaterThan(10);
    });

    it('answers 404 to a request for the author sitemap of Rank Math', function () {
        expect($this->hooks->refuseRankMathRequest(['sitemap' => 'author', 'sitemap_n' => '1']))->toBe([
            'error' => '404',
        ]);
    });

    it('leaves the other sitemaps and requests alone', function (array $vars) {
        expect($this->hooks->refuseRankMathRequest($vars))->toBe($vars);
    })->with([
        'a sitemap' => [['sitemap' => 'post']],
        'a page' => [['pagename' => 'about']],
        'an author archive' => [['author_name' => 'admin']],
    ]);

    it('hooks the core sitemap with both arguments', function () {
        $attribute = new ReflectionMethod(
            DisableAuthorSitemap::class,
            'removeCoreProvider',
        )->getAttributes(AsFilter::class)[0]->newInstance();

        expect($attribute->hook)->toBe('wp_sitemaps_add_provider');
        expect($attribute->acceptedArgs)->toBe(2);
    });
});
