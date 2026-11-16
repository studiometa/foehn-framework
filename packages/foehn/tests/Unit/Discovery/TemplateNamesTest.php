<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TemplateNames;

describe('TemplateNames', function () {
    it('names a single post from the most specific to the least', function () {
        expect(TemplateNames::single('product', 'red', null))->toBe(['single-product-red', 'single-product', 'single']);
        expect(TemplateNames::single('product', null, null))->toBe(['single-product', 'single']);
    });

    it('gives a post of type post no single-post', function () {
        expect(TemplateNames::single('post', 'hello', null))->toBe(['single-post-hello', 'single']);
    });

    it('names a page by its slug, then its ID', function () {
        expect(TemplateNames::page('about', 12, null))->toBe(['page-about', 'page-12', 'page']);
        expect(TemplateNames::page(null, null, null))->toBe(['page']);
    });

    it('puts the page template a post selects first, as the WordPress hierarchy does', function () {
        expect(TemplateNames::page('contact', 5, 'page-full-width.php'))->toBe([
            'page-full-width',
            'page-contact',
            'page-5',
            'page',
        ]);
        expect(TemplateNames::page('contact', 5, 'templates/landing.php'))->toBe([
            'templates/landing',
            'page-contact',
            'page-5',
            'page',
        ]);
        expect(TemplateNames::single('product', 'red', 'single-wide.php'))->toBe([
            'single-wide',
            'single-product-red',
            'single-product',
            'single',
        ]);
    });

    it('adds no page template for the default one', function () {
        expect(TemplateNames::page('contact', 5, ''))->toBe(['page-contact', 'page-5', 'page']);
        expect(TemplateNames::page('contact', 5, 'default'))->toBe(['page-contact', 'page-5', 'page']);
    });

    it('names a category, a tag and a custom taxonomy archive', function () {
        expect(TemplateNames::category('news'))->toBe(['category-news', 'category']);
        expect(TemplateNames::tag(null))->toBe(['tag']);
        expect(TemplateNames::taxonomy('brand', 'acme'))->toBe(['taxonomy-brand-acme', 'taxonomy-brand', 'taxonomy']);
        expect(TemplateNames::taxonomy(null, null))->toBe(['taxonomy']);
    });
});
