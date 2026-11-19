<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Indexer;
use Studiometa\Foehn\Search\Jobs\IndexPosts;
use Studiometa\Foehn\Search\Jobs\IndexPostsHandler;
use Studiometa\Foehn\Search\Languages;
use Studiometa\Foehn\Search\SearchDocuments;
use Studiometa\Foehn\Search\Tests\Fixtures\PostDocumentFixture;

beforeEach(function () {
    requiresLoupe();
    wp_stub_reset();
    bootTestContainer();
    $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français'];
    $GLOBALS['pll_stub_post_translations'] = [1 => ['en' => 1, 'fr' => 2], 2 => ['en' => 1, 'fr' => 2]];

    $documents = new SearchDocuments();
    $documents->register('post', PostDocumentFixture::class);
    $this->index = testSearchIndex($this->directory);
    $this->indexer = new Indexer($documents, $this->index, new Languages());
});

afterEach(function () {
    tearDownTestContainer();
    removeDirectory($this->directory ?? null);
});

it('indexes a post in the index of its language only', function () {
    searchPost(1, ['post_title' => 'Bread']);
    searchPost(2, ['post_title' => 'Pain']);

    new IndexPostsHandler($this->indexer)(new IndexPosts([1, 2]));

    expect($this->index->ids('en', 'bread'))->toBe([1]);
    expect($this->index->ids('fr', 'pain'))->toBe([2]);
    expect($this->index->ids('en', 'pain'))->toBe([]);
});

it('moves a post to the index of its new language', function () {
    searchPost(1, ['post_title' => 'Bread']);
    $this->indexer->index(1);

    $GLOBALS['pll_stub_post_translations'][1] = ['fr' => 1];
    $this->indexer->index(1);

    expect($this->index->count('en'))->toBe(0);
    expect($this->index->ids('fr', 'bread'))->toBe([1]);
});

it('indexes a post in every index when its post type is not translated', function () {
    $GLOBALS['pll_stub_untranslated_post_types'] = ['post'];
    searchPost(3, ['post_title' => 'Corridors']);

    $this->indexer->index(3);

    expect($this->index->ids('en', 'corridors'))->toBe([3]);
    expect($this->index->ids('fr', 'corridors'))->toBe([3]);
});

it('takes a post out of every index when it is unpublished or deleted', function () {
    searchPost(1, ['post_title' => 'Bread']);
    $this->indexer->index(1);

    searchPost(1, ['post_title' => 'Bread', 'post_status' => 'trash']);
    $this->indexer->index(1);
    expect($this->index->count('en'))->toBe(0);

    searchPost(1, ['post_title' => 'Bread']);
    $this->indexer->index(1);
    unset($GLOBALS['wp_stub_posts'][1]);
    $this->indexer->index(1);
    expect($this->index->count('en'))->toBe(0);
});

it('rebuilds every index from the published posts of every language', function () {
    $this->index->save('en', ['id' => 99, 'post_type' => 'post', 'title' => 'Stale']);
    $GLOBALS['wp_stub_get_posts'] = static fn(array $args): array => (
        $args['paged'] === 1 ? [searchPost(1, ['post_title' => 'Bread']), searchPost(2, ['post_title' => 'Pain'])] : []
    );

    expect($this->indexer->rebuild())->toBe(['en' => 1, 'fr' => 1]);
    expect($this->index->ids('en', 'stale'))->toBe([]);
    expect($this->index->ids('fr', 'pain'))->toBe([2]);

    $calls = array_values(array_filter(
        $GLOBALS['wp_stub_calls'],
        static fn(array $call): bool => $call['function'] === 'get_posts',
    ));
    $args = $calls[0]['args']['args'] ?? [];
    expect($args)->toMatchArray(['post_type' => ['post'], 'post_status' => 'publish', 'lang' => '']);
});

it('rebuilds a post of a post type that is not translated into every index', function () {
    $GLOBALS['pll_stub_untranslated_post_types'] = ['post'];
    $GLOBALS['wp_stub_get_posts'] = static fn(array $args): array => (
        $args['paged'] === 1 ? [searchPost(3, ['post_title' => 'Corridors'])] : []
    );

    expect($this->indexer->rebuild())->toBe(['en' => 1, 'fr' => 1]);
    expect($this->index->ids('fr', 'corridors'))->toBe([3]);
});
