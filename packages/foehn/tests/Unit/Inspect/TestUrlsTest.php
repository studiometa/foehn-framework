<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTemplateController;
use Studiometa\Foehn\Discovery\TemplateControllerMap;
use Studiometa\Foehn\Inspect\TestUrls;

/**
 * Test URLs for a map where each controller claims the given templates.
 *
 * @param array<string, string|list<string>> $claims Templates by controller class
 */
function testUrls(array $claims): TestUrls
{
    $items = [];

    foreach ($claims as $className => $templates) {
        $items[] = ['attribute' => new AsTemplateController($templates), 'className' => $className];
    }

    return TestUrls::sample(TemplateControllerMap::fromItems($items));
}

function samplePost(int $id, string $name, string $type = 'post'): WP_Post
{
    $post = new WP_Post();
    $post->ID = $id;
    $post->post_name = $name;
    $post->post_type = $type;

    return $post;
}

function sampleTerm(string $slug, string $taxonomy): WP_Term
{
    $term = new WP_Term();
    $term->slug = $slug;
    $term->taxonomy = $taxonomy;

    return $term;
}

/**
 * Answers get_posts() with one post per post type.
 *
 * @param array<string, WP_Post> $posts
 */
function newestPosts(array $posts): void
{
    $GLOBALS['wp_stub_get_posts'] = static fn(array $args): array => (
        isset($posts[$args['post_type']]) ? [$posts[$args['post_type']]] : []
    );
}

beforeEach(fn() => wp_stub_reset());

describe('TestUrls', function () {
    it('gives the front page, a search and a missing page', function () {
        $urls = testUrls(['App\\Front' => 'front-page', 'App\\Search' => 'search', 'App\\NotFound' => '404']);

        expect($urls->of('front-page'))->toBe(['url' => 'http://example.com/', 'noUrl' => null]);
        expect($urls->of('search')['url'])->toBe('http://example.com/?s=test');
        expect($urls->of('404')['url'])->toBe('http://example.com/foehn-not-found/');
    });

    it('gives the newest published post of the type', function () {
        $GLOBALS['wp_stub_public_post_types'] = ['product'];
        newestPosts(['post' => samplePost(1, 'hello'), 'product' => samplePost(7, 'red-shoe', 'product')]);

        $urls = testUrls(['App\\Single' => 'single', 'App\\Product' => 'single-product']);

        expect($urls->of('single')['url'])->toBe('http://example.com/hello/');
        expect($urls->of('single-product')['url'])->toBe('http://example.com/red-shoe/');
        expect(wp_stub_get_calls('get_posts')[0]['args']['args'])->toMatchArray([
            'post_status' => 'publish',
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    });

    it('gives a wildcard the first post it renders, and says what takes a template it loses', function () {
        newestPosts(['post' => samplePost(1, 'hello')]);

        // `single-*` claims `single-post-hello`, which is more specific than `single`.
        $urls = testUrls(['App\\Singles' => 'single-*', 'App\\Single' => 'single']);

        expect($urls->of('single-*')['url'])->toBe('http://example.com/hello/');
        expect($urls->of('single'))->toBe(['url' => null, 'noUrl' => "Its sample URLs render 'single-*' instead."]);
    });

    it('says when no published post renders a single template', function () {
        $GLOBALS['wp_stub_public_post_types'] = ['event'];

        expect(testUrls(['App\\Event' => 'single-event'])->of('single-event'))->toBe([
            'url' => null,
            'noUrl' => 'The newest published post of each type does not render it.',
        ]);
    });

    it('gives the archive of a post type that has one', function () {
        $GLOBALS['wp_stub_public_post_types'] = ['product', 'event'];
        $GLOBALS['wp_stub_archive_links'] = ['product' => 'http://example.com/products/'];

        $urls = testUrls(['App\\Archive' => ['archive-product', 'archive-event']]);

        expect($urls->of('archive-product')['url'])->toBe('http://example.com/products/');
        expect($urls->of('archive-event')['noUrl'])->toBe('No public post type with an archive renders it.');
    });

    it('gives a page that is neither the front page nor the posts page, and the posts page as home', function () {
        $GLOBALS['wp_stub_options'] = ['show_on_front' => 'page', 'page_on_front' => 2, 'page_for_posts' => 3];
        $GLOBALS['wp_stub_permalinks'] = [3 => 'http://example.com/blog/'];
        newestPosts(['page' => samplePost(4, 'about', 'page')]);

        $urls = testUrls(['App\\Page' => 'page', 'App\\Blog' => 'home']);

        expect($urls->of('page')['url'])->toBe('http://example.com/about/');
        expect($urls->of('home')['url'])->toBe('http://example.com/blog/');
        $pageQuery = array_values(array_filter(
            array_column(array_column(wp_stub_get_calls('get_posts'), 'args'), 'args'),
            static fn(array $args): bool => $args['post_type'] === 'page',
        ));
        expect($pageQuery[0]['post__not_in'])->toBe([2, 3]);
    });

    it('gives a page its slug template when a controller claims it', function () {
        newestPosts(['page' => samplePost(4, 'about', 'page')]);

        $urls = testUrls(['App\\Page' => 'page', 'App\\About' => 'page-about']);

        expect($urls->of('page-about')['url'])->toBe('http://example.com/about/');
        expect($urls->of('page')['noUrl'])->toBe("Its sample URLs render 'page-about' instead.");
    });

    it('says why home has no URL when the front page lists the posts', function () {
        expect(testUrls(['App\\Blog' => 'home'])->of('home')['noUrl'])
            ->toBe('The front page lists the posts and renders front-page. Set a posts page in Settings > Reading.');
    });

    it('gives a term link for a category, a tag and a custom taxonomy', function () {
        $GLOBALS['wp_stub_public_taxonomies'] = ['brand'];
        $GLOBALS['wp_stub_terms'] = [
            'category' => [sampleTerm('news', 'category')],
            'brand' => [sampleTerm('acme', 'brand')],
        ];

        $urls = testUrls(['App\\Terms' => ['category', 'tag', 'taxonomy-brand']]);

        expect($urls->of('category')['url'])->toBe('http://example.com/category/news/');
        expect($urls->of('taxonomy-brand')['url'])->toBe('http://example.com/brand/acme/');
        expect($urls->of('tag'))->toBe([
            'url' => null,
            'noUrl' => 'The term with the most posts of each taxonomy does not render it.',
        ]);
    });

    it('gives the author and the month of the newest post', function () {
        $hello = samplePost(1, 'hello');
        $hello->post_author = 5;
        $hello->post_date = '2026-03-14 09:00:00';
        newestPosts(['post' => $hello]);

        $urls = testUrls(['App\\Archives' => ['author', 'date']]);

        expect($urls->of('author')['url'])->toBe('http://example.com/author/5/');
        expect($urls->of('date')['url'])->toBe('http://example.com/2026/03/');
    });

    it('says when only an older post has the slug a template names', function () {
        newestPosts(['page' => samplePost(4, 'about', 'page')]);

        expect(testUrls(['App\\Contact' => 'page-contact'])->of('page-contact'))->toBe([
            'url' => null,
            'noUrl' => 'The newest published post of each type does not render it.',
        ]);
    });

    it('does not call a pattern with a wildcard in its first segment unknown', function () {
        expect(testUrls(['App\\Singles' => 'sing*'])->of('sing*')['noUrl'])->toBe('No sample URL renders it.');
    });

    it('says when Føhn gives no request a template name', function () {
        expect(testUrls(['App\\Terms' => 'tax-*'])->of('tax-*')['noUrl'])
            ->toBe('Føhn gives no request a template name like this.');
    });
});
