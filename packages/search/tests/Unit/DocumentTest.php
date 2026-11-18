<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Document;
use Studiometa\Foehn\Search\Indexer;
use Studiometa\Foehn\Search\Languages;
use Studiometa\Foehn\Search\SearchDocuments;
use Studiometa\Foehn\Search\Tests\Fixtures\PostDocumentFixture;

beforeEach(function () {
    wp_stub_reset();
    bootTestContainer();
    $this->documents = new SearchDocuments();
    $this->documents->register('post', PostDocumentFixture::class);
    $this->indexer = new Indexer($this->documents, testSearchIndex(), new Languages());
});

afterEach(function () {
    tearDownTestContainer();
});

it('gives the title, the excerpt and the content as plain text', function () {
    $post = searchPost(1, [
        'post_title' => 'Caf&eacute; &amp; <em>croissants</em>',
        'post_excerpt' => '<p>Short</p>',
        'post_content' => "<!-- wp:paragraph -->\n<p>Fresh   bread [gallery ids=\"1\"] every\nmorning.</p>\n<!-- /wp:paragraph -->",
        'post_date_gmt' => '2026-11-18 08:00:00',
    ]);

    expect(Document::fromPost($post))->toBe([
        'title' => 'Café & croissants',
        'excerpt' => 'Short',
        'content' => 'Fresh bread every morning.',
        'terms' => [],
        'date' => strtotime('2026-11-18 08:00:00 UTC'),
    ]);
});

it('gives the names of the terms of every taxonomy of the post, once', function () {
    $GLOBALS['wp_stub_object_taxonomies']['post'] = ['category', 'post_tag'];
    $bread = new WP_Term();
    $bread->name = 'Bread';
    $morning = new WP_Term();
    $morning->name = 'Morning';
    $GLOBALS['wp_stub_post_terms'][2] = ['category' => [$bread], 'post_tag' => [$morning, $bread]];

    expect(Document::fromPost(searchPost(2))['terms'])->toBe(['Bread', 'Morning']);
});

it('adds the id and the post type to the fields of the document class', function () {
    $document = $this->indexer->document(searchPost(3, ['post_title' => 'Hello']));

    expect($document)->toMatchArray(['id' => 3, 'post_type' => 'post', 'title' => 'Hello', 'reference' => 'REF-3']);
});

it('keeps out a post that is not published, has a password or has no document class', function (array $fields) {
    expect($this->indexer->document(searchPost(4, $fields)))->toBeNull();
})->with([
    'draft' => [['post_status' => 'draft']],
    'trash' => [['post_status' => 'trash']],
    'password' => [['post_password' => 'secret']],
    'another post type' => [['post_type' => 'page']],
]);

it('keeps out a post its document class gives no document for', function () {
    expect($this->indexer->document(searchPost(5, ['post_name' => 'private-notes'])))->toBeNull();
});
