<?php

declare(strict_types=1);

use Studiometa\Foehn\Search\Query\SearchGroups;
use Studiometa\Foehn\Search\Query\SearchQuery;
use Studiometa\Foehn\Search\SearchDocuments;
use Studiometa\Foehn\Search\Tests\Fixtures\PostDocumentFixture;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wpdb'] = new wpdb();
    $documents = new SearchDocuments();
    $documents->register('post', PostDocumentFixture::class);
    $documents->register('event', PostDocumentFixture::class);
    $this->groups = new SearchGroups($documents);
    $_SERVER['REQUEST_URI'] = '/?s=bread&category=news&paged=2';
});

it('groups the results by post type, in order of relevance, with a link to each type', function () {
    foreach ([1, 2, 3, 4] as $id) {
        searchPost($id);
    }

    $GLOBALS['wp_stub_query_posts'] = static fn(array $vars): array => [4, 1, 3, 2];
    $GLOBALS['wpdb']->results = [
        (object) ['ID' => 1, 'post_type' => 'post'],
        (object) ['ID' => 2, 'post_type' => 'post'],
        (object) ['ID' => 3, 'post_type' => 'post'],
        (object) ['ID' => 4, 'post_type' => 'event'],
    ];
    $GLOBALS['wp_stub_post_type_labels'] = ['post' => 'Posts', 'event' => 'Events'];

    $query = new WP_Query();
    $query->set('s', 'bread');
    $query->set('post_type', 'post');
    $query->set('paged', 2);
    $query->set(SearchQuery::QUERY_VAR, true);

    $groups = $this->groups->for($query, perGroup: 2);

    expect(array_map(static fn($group) => [$group->postType, $group->label, $group->count], $groups))->toBe([
        ['event', 'Events', 1],
        ['post',  'Posts',  3],
    ]);
    expect(array_map(static fn(WP_Post $post) => $post->ID, $groups[1]->posts))->toBe([1, 3]);
    expect($groups[1]->url)->toContain('post_type=post')->toContain('category=news')->not->toContain('paged');
});

it('counts every searchable post type with the other filters kept', function () {
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = static function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [];
    };

    $query = new WP_Query();
    $query->set('post_type', 'post');
    $query->set('category_name', 'news');
    $query->set('paged', 3);
    $query->set(SearchQuery::QUERY_VAR, true);

    expect($this->groups->for($query))->toBe([]);
    expect($seen)->toMatchArray([
        'post_type' => ['post', 'event'],
        'category_name' => 'news',
        'fields' => 'ids',
        'posts_per_page' => -1,
    ]);
    expect($seen)->not->toHaveKey('paged');
});

it('gives no group on a query the index did not match', function () {
    expect($this->groups->for(new WP_Query()))->toBe([]);
});
