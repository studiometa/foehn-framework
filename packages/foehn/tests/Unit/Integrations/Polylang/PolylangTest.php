<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\Multilingual;
use Studiometa\Foehn\Integrations\Polylang\Polylang;

use function Studiometa\Foehn\multilingual;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français'];
    $this->polylang = new Polylang();
});

describe('Polylang', function () {
    it('is the multilingual adapter when Polylang has languages', function () {
        expect(multilingual())->toBeInstanceOf(Polylang::class);
        expect(multilingual())->toBeInstanceOf(Multilingual::class);
    });

    it('is off without a language', function () {
        $GLOBALS['pll_stub_languages'] = [];

        expect(Polylang::isActive())->toBeFalse();
        expect(multilingual())->toBeNull();
    });

    it('gives the languages as code => name, and the default one', function () {
        expect($this->polylang->languages())->toBe(['en' => 'English', 'fr' => 'Français']);
        expect($this->polylang->defaultLanguage())->toBe('en');
    });

    it('gives the current language, and the default one before Polylang has found it', function () {
        expect($this->polylang->currentLanguage())->toBe('en');

        $GLOBALS['pll_stub_current'] = 'fr';

        expect($this->polylang->currentLanguage())->toBe('fr');
    });

    it('gives no admin language when all languages are selected', function () {
        expect($this->polylang->adminLanguage())->toBeNull();

        $GLOBALS['pll_stub_current'] = 'fr';

        expect($this->polylang->adminLanguage())->toBe('fr');
    });

    it('gives the published translations of a post', function () {
        $draft = new WP_Post();
        $draft->ID = 13;
        $draft->post_status = 'draft';
        $GLOBALS['wp_stub_posts'][13] = $draft;
        $GLOBALS['pll_stub_post_translations'][10] = ['en' => 10, 'fr' => 11, 'de' => 13];
        $post = new WP_Post();
        $post->ID = 10;

        expect($this->polylang->translations($post))->toBe(['en' => 10, 'fr' => 11]);
    });

    it('gives the language of a post or a term, whatever its status', function () {
        $draft = new WP_Post();
        $draft->ID = 13;
        $draft->post_status = 'draft';
        $GLOBALS['wp_stub_posts'][13] = $draft;
        $GLOBALS['pll_stub_post_translations'][13] = ['en' => 10, 'fr' => 13];
        $GLOBALS['pll_stub_term_translations'][6] = ['en' => 5, 'fr' => 6];
        $term = new WP_Term();
        $term->term_id = 6;
        $untranslated = new WP_Post();
        $untranslated->ID = 20;

        expect($this->polylang->language($draft))->toBe('fr');
        expect($this->polylang->language($term))->toBe('fr');
        expect($this->polylang->language($untranslated))->toBeNull();
    });

    it('gives the translations of a term', function () {
        $GLOBALS['pll_stub_term_translations'][5] = ['en' => 5, 'fr' => 6];
        $term = new WP_Term();
        $term->term_id = 5;

        expect($this->polylang->translations($term))->toBe(['en' => 5, 'fr' => 6]);
    });

    it('gives the URL of a translation, and the home page of a language without one', function () {
        $GLOBALS['pll_stub_post_translations'][10] = ['en' => 10, 'fr' => 11];
        $GLOBALS['wp_stub_permalinks'][11] = 'http://example.com/fr/bonjour/';
        $post = new WP_Post();
        $post->ID = 10;

        expect($this->polylang->url('fr', $post))->toBe('http://example.com/fr/bonjour/');
        expect($this->polylang->url('de', $post))->toBe('http://example.com/de/');
        expect($this->polylang->url('fr'))->toBe('http://example.com/fr/');
        expect($this->polylang->url('en'))->toBe('http://example.com/');
    });
});
