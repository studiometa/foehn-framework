<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Views\Twig\LanguagesExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use function Studiometa\Foehn\languages;

describe('LanguagesExtension', function () {
    beforeEach(function () {
        wp_stub_reset();
        $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch'];
        $GLOBALS['pll_stub_current'] = 'en';
        $GLOBALS['pll_stub_post_translations'][10] = ['en' => 10, 'fr' => 11];
        $GLOBALS['wp_stub_permalinks'][10] = 'http://example.com/hello/';
        $GLOBALS['wp_stub_permalinks'][11] = 'http://example.com/fr/bonjour/';
        $post = new WP_Post();
        $post->ID = 10;
        $GLOBALS['wp_stub_queried_object'] = $post;
    });

    afterEach(function () {
        unset($GLOBALS['wp_stub_queried_object']);
    });

    it('is discovered, so templates can use its function', function () {
        expect(new ReflectionClass(LanguagesExtension::class)->getAttributes(AsTwigExtension::class))->toHaveCount(1);
    });

    it('gives each language, the translation of the page or the home page of the language', function () {
        expect(languages())->toBe([
            [
                'code' => 'en',
                'name' => 'English',
                'url' => 'http://example.com/hello/',
                'current' => true,
                'translated' => true,
            ],
            [
                'code' => 'fr',
                'name' => 'Français',
                'url' => 'http://example.com/fr/bonjour/',
                'current' => false,
                'translated' => true,
            ],
            [
                'code' => 'de',
                'name' => 'Deutsch',
                'url' => 'http://example.com/de/',
                'current' => false,
                'translated' => false,
            ],
        ]);
    });

    it('links every language to its home page on a page without an object', function () {
        unset($GLOBALS['wp_stub_queried_object']);

        expect(array_column(languages(), 'url'))->toBe([
            'http://example.com/',
            'http://example.com/fr/',
            'http://example.com/de/',
        ]);
        expect(array_column(languages(), 'translated'))->toBe([false, false, false]);
    });

    it('gives nothing on a site with one language', function () {
        $GLOBALS['pll_stub_languages'] = [];

        expect(languages())->toBe([]);
    });

    it('gives the switcher to a template', function () {
        $twig = new Environment(new ArrayLoader([
            'switcher' => '{% for item in languages() %}[{{ item.code }}|{{ item.url }}|{{ item.current ? 1 : 0 }}]{% endfor %}',
        ]));
        $twig->addExtension(new LanguagesExtension());

        expect($twig->render('switcher'))
            ->toBe(
                '[en|http://example.com/hello/|1][fr|http://example.com/fr/bonjour/|0][de|http://example.com/de/|0]',
            );
    });
});
