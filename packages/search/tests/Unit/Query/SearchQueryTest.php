<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Languages;
use Studiometa\Foehn\Search\Query\SearchQuery;
use Studiometa\Foehn\Search\SearchDocuments;
use Studiometa\Foehn\Search\Tests\Fixtures\PostDocumentFixture;

function searchMainQuery(string $s, array $vars = []): WP_Query
{
    $query = new WP_Query();
    $query->is_search = true;
    $query->set('s', $s);

    foreach ($vars as $key => $value) {
        $query->set($key, $value);
    }

    return $query;
}

beforeEach(function () {
    requiresLoupe();
    wp_stub_reset();
    $documents = new SearchDocuments();
    $documents->register('post', PostDocumentFixture::class);
    $documents->register('event', PostDocumentFixture::class);
    $this->index = testSearchIndex($this->directory);
    $this->index->saveMany('en', [
        ['id' => 1, 'post_type' => 'post', 'title' => 'Croissants', 'content' => 'With sourdough.'],
        ['id' => 2, 'post_type' => 'post', 'title' => 'Sourdough'],
        ['id' => 4, 'post_type' => 'post', 'title' => "L'atelier du pain"],
        ['id' => 5, 'post_type' => 'post', 'title' => 'Crème brûlée'],
    ]);
    $this->index->save('fr', ['id' => 3, 'post_type' => 'post', 'title' => 'Levain']);
    $this->hook = new SearchQuery($this->index, new Languages(), $documents);
});

afterEach(function () {
    unset($_GET['s']);
    removeDirectory($this->directory ?? null);
});

it('gives the main search query every match in order of relevance', function () {
    $query = searchMainQuery('sourdouh');

    $this->hook->onPreGetPosts($query);

    expect($query->get('post__in'))->toBe([2, 1]);
    expect($query->get('orderby'))->toBe('post__in');
    expect($query->get('post_type'))->toBe(['post', 'event']);
    expect($query->get(SearchQuery::QUERY_VAR))->toBeTrue();
});

it('reads a search from the query string without the slashes WordPress adds', function () {
    // WordPress adds slashes to $_GET, and the query var keeps them until parse_search().
    $_GET['s'] = "l\\'atelier";
    $query = searchMainQuery("l\\'atelier");

    $this->hook->onPreGetPosts($query);

    expect($query->get('post__in'))->toBe([4]);
});

it('decodes a search from a /search/<term>/ URL', function () {
    $query = searchMainQuery('cr%C3%A8me%20br%C3%BBl%C3%A9e');

    $this->hook->onPreGetPosts($query);

    expect($query->get('post__in'))->toBe([5]);
});

it('keeps the post type a visitor asked for', function () {
    $query = searchMainQuery('sourdough', ['post_type' => 'event']);

    $this->hook->onPreGetPosts($query);

    expect($query->get('post_type'))->toBe('event');
});

it('gives an empty search no result rather than every post', function (string $s) {
    $query = searchMainQuery($s);

    $this->hook->onPreGetPosts($query);

    expect($query->get('post__in'))->toBe([0]);
})->with(['empty' => [''], 'blank' => ['  '], 'no match' => ['zzzzzz']]);

it('searches the index of the current language', function () {
    $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français'];
    $GLOBALS['pll_stub_current'] = 'fr';
    $query = searchMainQuery('levain');

    $this->hook->onPreGetPosts($query);

    expect($query->get('post__in'))->toBe([3]);
});

it('lifts the language clause of the multilingual plugin, and keeps the other clauses', function () {
    $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français'];
    $series = ['taxonomy' => 'series', 'field' => 'slug', 'terms' => ['architecture']];
    $query = searchMainQuery('sourdough', [
        'lang' => 'en',
        'tax_query' => [['taxonomy' => 'language', 'field' => 'term_taxonomy_id', 'terms' => [25]], $series],
    ]);

    $this->hook->onPreGetPosts($query);

    expect($query->get('lang'))->toBe('');
    expect(array_values($query->get('tax_query')))->toBe([$series]);
});

it('leaves alone a query that is not the main search query', function (bool $admin, bool $main, bool $search) {
    $GLOBALS['wp_stub_is_admin'] = $admin;
    $query = searchMainQuery('sourdough');
    $query->set_main_query($main);
    $query->is_search = $search;

    $this->hook->onPreGetPosts($query);

    expect($query->get('post__in', null))->toBeNull();
})->with([
    'admin' => [true, true, true],
    'secondary' => [false, false, true],
    'archive' => [false, true, false],
]);

it('takes the LIKE search of WordPress off a query the index matched, and its copies', function () {
    $query = searchMainQuery('sourdough');
    $this->hook->onPreGetPosts($query);
    $copy = new WP_Query($query->query_vars);

    expect($this->hook->withoutLikeSearch(' AND (post_title LIKE ...)', $copy))->toBe('');
    expect($this->hook->withoutLikeSearch(' AND (post_title LIKE ...)', new WP_Query(['s' => 'x'])))
        ->toBe(' AND (post_title LIKE ...)');
});
