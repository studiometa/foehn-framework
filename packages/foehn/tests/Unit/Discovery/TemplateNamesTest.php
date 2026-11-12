<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TemplateNames;

describe('TemplateNames', function () {
    it('names a single post from the most specific to the least', function () {
        expect(TemplateNames::single('product', 'red'))->toBe(['single-product-red', 'single-product', 'single']);
        expect(TemplateNames::single('product', null))->toBe(['single-product', 'single']);
    });

    it('gives a post of type post no single-post', function () {
        expect(TemplateNames::single('post', 'hello'))->toBe(['single-post-hello', 'single']);
    });

    it('names a page by its slug, then its ID', function () {
        expect(TemplateNames::page('about', 12))->toBe(['page-about', 'page-12', 'page']);
        expect(TemplateNames::page(null, null))->toBe(['page']);
    });

    it('names a category, a tag and a custom taxonomy archive', function () {
        expect(TemplateNames::category('news'))->toBe(['category-news', 'category']);
        expect(TemplateNames::tag(null))->toBe(['tag']);
        expect(TemplateNames::taxonomy('brand', 'acme'))->toBe(['taxonomy-brand-acme', 'taxonomy-brand', 'taxonomy']);
        expect(TemplateNames::taxonomy(null, null))->toBe(['taxonomy']);
    });
});
