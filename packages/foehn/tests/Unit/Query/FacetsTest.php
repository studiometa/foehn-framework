<?php

declare(strict_types=1);

use Studiometa\Foehn\Query\Facets;

/**
 * Counted filter options.
 *
 * The assertion this file exists for is the third one: a facet is counted with its own
 * filter lifted. With the filter left in, selecting one series makes every other series
 * report zero, and a visitor who wants two can never pick the second — the control
 * silently stops working, which is worse than having no counts at all.
 */

function facetTerm(int $id, string $slug): WP_Term
{
    $term = new WP_Term();
    $term->term_id = $id;
    $term->slug = $slug;
    $term->name = ucfirst($slug);
    $term->taxonomy = 'genre';

    return $term;
}

function facetQuery(array $vars): WP_Query
{
    $query = new WP_Query();

    foreach ($vars as $key => $value) {
        $query->set($key, $value);
    }

    return $query;
}

beforeEach(function () {
    wp_stub_reset();

    $GLOBALS['wp_stub_terms']['genre'] = [facetTerm(1, 'rock'), facetTerm(2, 'jazz'), facetTerm(3, 'blues')];
    $GLOBALS['wp_stub_query_posts'] = [10, 11, 12];

    $GLOBALS['wpdb'] = new wpdb();
    $GLOBALS['wpdb']->results = [
        (object) ['term_id' => 1, 'matched' => 2],
        (object) ['term_id' => 2, 'matched' => 1],
    ];

    $this->facets = new Facets();
});

it('counts each term against the current view', function () {
    $options = $this->facets->for('genre', facetQuery(['post_type' => 'post']));

    expect($options)->toHaveCount(3);
    expect($options[0]->term->slug)->toBe('rock');
    expect($options[0]->count)->toBe(2);
    expect($options[1]->count)->toBe(1);
    // A term the grouped count did not mention has none, which is a dead end a facet
    // should say out loud rather than let a visitor discover.
    expect($options[2]->count)->toBe(0);
    expect($options[2]->isEmpty())->toBeTrue();
});

it('counts a facet with its own filter lifted, and no other', function () {
    // The vars the secondary query is built from are what this asserts on: `genre` has
    // to be gone, and every other constraint has to survive.
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [10, 11];
    };

    $this->facets->for('genre', facetQuery([
        'post_type' => 'project',
        'genre' => 'rock',
        'genre__and' => 'jazz',
        'year' => '2026',
    ]));

    expect($seen)->not->toBeNull();
    expect($seen)->not->toHaveKey('genre');
    expect($seen)->not->toHaveKey('genre__and');
    expect($seen)->toHaveKey('year');
    expect($seen['post_type'])->toBe('project');
});

it('drops the taxonomy and term pair WordPress records the queried object under', function () {
    // The case a stub cannot show and a real WP_Query does: `parse_tax_query()` never
    // writes its parsed `tax_query` back into `query_vars`, it leaves `taxonomy` and
    // `term` there and rebuilds the constraint from them. Keeping them counted every
    // facet against its own filter, so only the selected term had a count.
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [10];
    };

    $this->facets->for('genre', facetQuery([
        'genre' => 'rock',
        'taxonomy' => 'genre',
        'term' => 'rock',
    ]));

    expect($seen)->not->toHaveKey('taxonomy');
    expect($seen)->not->toHaveKey('term');
});

it('leaves another taxonomy queried object alone', function () {
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [10];
    };

    $this->facets->for('genre', facetQuery(['taxonomy' => 'mood', 'term' => 'calm']));

    expect($seen['taxonomy'])->toBe('mood');
    expect($seen['term'])->toBe('calm');
});

it('drops this taxonomy from a parsed tax_query and keeps the others', function () {
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [10];
    };

    $this->facets->for('genre', facetQuery([
        'tax_query' => [
            ['taxonomy' => 'genre', 'terms' => ['rock']],
            ['taxonomy' => 'mood', 'terms' => ['calm']],
        ],
    ]));

    $clauses = array_values($seen['tax_query']);

    expect($clauses)->toHaveCount(1);
    expect($clauses[0]['taxonomy'])->toBe('mood');
});

it('keeps the relation of a tax_query it has taken a clause out of', function () {
    // Renumbering the array turns `['relation' => 'AND']` into `[0 => 'AND']`, which
    // WordPress reads as a clause and cannot make sense of. The keys stay put.
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [10];
    };

    $this->facets->for('genre', facetQuery([
        'tax_query' => [
            'relation' => 'AND',
            ['taxonomy' => 'genre', 'terms' => ['rock']],
            ['taxonomy' => 'mood', 'terms' => ['calm']],
        ],
    ]));

    expect($seen['tax_query']['relation'])->toBe('AND');
});

it('drops a tax_query left with nothing but its relation', function () {
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [10];
    };

    $this->facets->for('genre', facetQuery([
        'tax_query' => ['relation' => 'AND', ['taxonomy' => 'genre', 'terms' => ['rock']]],
    ]));

    expect($seen)->not->toHaveKey('tax_query');
});

it('asks for every matching post rather than the page being shown', function () {
    $seen = null;
    $GLOBALS['wp_stub_query_posts'] = function (array $vars) use (&$seen): array {
        $seen = $vars;

        return [10];
    };

    $this->facets->for('genre', facetQuery(['posts_per_page' => 10, 'paged' => 3]));

    // Options derived from one page of results change as the visitor pages through
    // them, and hide a term that only appears further down.
    expect($seen['posts_per_page'])->toBeGreaterThan(10);
    expect($seen)->not->toHaveKey('paged');
    expect($seen['fields'])->toBe('ids');
    expect($seen['no_found_rows'])->toBeTrue();
});

it('marks the selected term active, in either spelling', function (mixed $value) {
    $options = $this->facets->for('genre', facetQuery(['genre' => $value]));

    expect($options[0]->active)->toBeTrue();
    expect($options[1]->active)->toBeFalse();
})->with([
    'the comma spelling' => ['rock'],
    'several comma values' => ['rock,blues'],
    'the array a checkbox group posts' => [['rock']],
]);

it('reads the active terms out of a parsed tax_query too', function () {
    $options = $this->facets->for('genre', facetQuery([
        'tax_query' => [['taxonomy' => 'genre', 'terms' => ['jazz']]],
    ]));

    expect($options[0]->active)->toBeFalse();
    expect($options[1]->active)->toBeTrue();
});

it('leaves the counts unknown rather than slow on a large view', function () {
    $GLOBALS['wp_stub_query_posts'] = range(1, Facets::MAX_COUNTED_POSTS + 1);

    $options = $this->facets->for('genre', facetQuery([]));

    // Null is not zero: nothing was counted, so nothing may be reported as empty.
    expect($options[0]->count)->toBeNull();
    expect($options[0]->isEmpty())->toBeFalse();
    expect($GLOBALS['wpdb']->queries)->toBe([]);
});

it('returns nothing for a taxonomy with no terms', function () {
    expect($this->facets->for('nonexistent', facetQuery([])))->toBe([]);
});

it('counts in one query whatever the number of terms', function () {
    $GLOBALS['wp_stub_terms']['genre'] = array_map(
        static fn(int $i): WP_Term => facetTerm($i, 'term-' . $i),
        range(1, 40),
    );

    $this->facets->for('genre', facetQuery([]));

    expect($GLOBALS['wpdb']->queries)->toHaveCount(1);
    expect($GLOBALS['wpdb']->queries[0])->toContain('GROUP BY');
});
