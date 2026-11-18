<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Discovery\SearchDocumentDiscovery;
use Studiometa\Foehn\Search\SearchDocuments;
use Studiometa\Foehn\Search\Tests\Fixtures\NotADocumentFixture;
use Studiometa\Foehn\Search\Tests\Fixtures\PostDocumentFixture;
use Tempest\Reflection\ClassReflector;

it('registers the post type of each search document class', function () {
    $documents = new SearchDocuments();
    $discovery = new SearchDocumentDiscovery($documents);

    $discovery->discover(testDiscoveryLocation(), new ClassReflector(PostDocumentFixture::class));
    $discovery->discover(testDiscoveryLocation(), new ClassReflector(NotADocumentFixture::class));
    $discovery->apply();

    expect($documents->postTypes())->toBe(['post']);
    expect($documents->classFor('post'))->toBe(PostDocumentFixture::class);
});

it('adds the index and query hooks when there is a search document', function () {
    wp_stub_reset();
    $discovery = new SearchDocumentDiscovery(new SearchDocuments());
    $discovery->discover(testDiscoveryLocation(), new ClassReflector(PostDocumentFixture::class));
    $discovery->apply();

    $hooks = array_map(
        static fn(array $call): string => $call['args']['hook'],
        array_filter($GLOBALS['wp_stub_calls'], static fn(array $call): bool => in_array(
            $call['function'],
            ['add_action', 'add_filter'],
            true,
        )),
    );

    expect(array_values($hooks))->toBe([
        'save_post',
        'deleted_post',
        'set_object_terms',
        'edited_term',
        'delete_term',
        'shutdown',
        'pre_get_posts',
        'posts_search',
    ]);
});

it('adds no hook to a site without a search document', function () {
    wp_stub_reset();
    new SearchDocumentDiscovery(new SearchDocuments())->apply();

    expect($GLOBALS['wp_stub_calls'])->toBe([]);
});

it('keeps its items through the discovery cache', function () {
    $source = new SearchDocumentDiscovery(new SearchDocuments());
    $source->discover(testDiscoveryLocation(), new ClassReflector(PostDocumentFixture::class));

    $documents = new SearchDocuments();
    restoreThroughCacheFile($source, new SearchDocumentDiscovery($documents))->apply();

    expect($documents->classFor('post'))->toBe(PostDocumentFixture::class);
});
