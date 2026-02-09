<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Config\QueryFiltersConfig;
use Studiometa\Foehn\Hooks\QueryFiltersHook;

beforeEach(function () {
    wp_stub_reset();
    $GLOBALS['wp_stub_is_admin'] = false;
});

describe('QueryFiltersHook', function () {
    it('has AsFilter attribute on registerQueryVars method', function () {
        $reflection = new ReflectionMethod(QueryFiltersHook::class, 'registerQueryVars');
        $attributes = $reflection->getAttributes(AsFilter::class);

        expect($attributes)->toHaveCount(1);

        $instance = $attributes[0]->newInstance();
        expect($instance->hook)->toBe('query_vars');
    });

    it('has AsAction attribute on applyFilters method', function () {
        $reflection = new ReflectionMethod(QueryFiltersHook::class, 'applyFilters');
        $attributes = $reflection->getAttributes(AsAction::class);

        expect($attributes)->toHaveCount(1);

        $instance = $attributes[0]->newInstance();
        expect($instance->hook)->toBe('pre_get_posts');
    });

    describe('registerQueryVars', function () {
        it('adds taxonomy query vars', function () {
            $config = new QueryFiltersConfig(taxonomies: [
                'genre' => ['in', 'not_in', 'and'],
            ]);
            $hook = new QueryFiltersHook($config);

            $result = $hook->registerQueryVars(['existing_var']);

            expect($result)->toContain('existing_var');
            expect($result)->toContain('genre');
            expect($result)->toContain('genre__not_in');
            expect($result)->toContain('genre__and');
        });

        it('adds public var query vars', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);
            $hook = new QueryFiltersHook($config);

            $result = $hook->registerQueryVars(['existing_var']);

            expect($result)->toContain('existing_var');
            expect($result)->toContain('posts_per_page');
        });

        it('preserves existing vars', function () {
            $config = new QueryFiltersConfig();
            $hook = new QueryFiltersHook($config);

            $result = $hook->registerQueryVars(['var1', 'var2']);

            expect($result)->toBe(['var1', 'var2']);
        });
    });

    describe('applyFilters', function () {
        it('skips non-main queries', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set_main_query(false);
            $query->set('genre', 'rock');

            $hook->applyFilters($query);

            // tax_query should not be set
            expect($query->get('tax_query'))->toBe('');
        });

        it('skips admin queries', function () {
            $GLOBALS['wp_stub_is_admin'] = true;

            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', 'rock');

            $hook->applyFilters($query);

            // tax_query should not be set
            expect($query->get('tax_query'))->toBe('');
        });

        it('applies taxonomy filter with IN operator', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', 'rock');

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery)->toBeArray();
            expect($taxQuery['relation'])->toBe('AND');
            expect($taxQuery[0]['taxonomy'])->toBe('genre');
            expect($taxQuery[0]['field'])->toBe('slug');
            expect($taxQuery[0]['terms'])->toBe(['rock']);
            expect($taxQuery[0]['operator'])->toBe('IN');
        });

        it('applies taxonomy filter with comma-separated values', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', 'rock,jazz,blues');

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery[0]['terms'])->toBe(['rock', 'jazz', 'blues']);
        });

        it('applies taxonomy filter with NOT IN operator', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['not_in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre__not_in', 'classical');

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery[0]['taxonomy'])->toBe('genre');
            expect($taxQuery[0]['terms'])->toBe(['classical']);
            expect($taxQuery[0]['operator'])->toBe('NOT IN');
        });

        it('applies taxonomy filter with AND operator', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['and']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre__and', 'rock,electronic');

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery[0]['taxonomy'])->toBe('genre');
            expect($taxQuery[0]['terms'])->toBe(['rock', 'electronic']);
            expect($taxQuery[0]['operator'])->toBe('AND');
        });

        it('applies multiple taxonomy filters', function () {
            $config = new QueryFiltersConfig(taxonomies: [
                'genre' => ['in'],
                'artist' => ['in'],
            ]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', 'rock');
            $query->set('artist', 'beatles');

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery)->toHaveCount(3); // 2 clauses + relation
            expect($taxQuery[0]['taxonomy'])->toBe('genre');
            expect($taxQuery[1]['taxonomy'])->toBe('artist');
        });

        it('merges with existing tax_query', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('tax_query', [
                [
                    'taxonomy' => 'existing',
                    'field' => 'term_id',
                    'terms' => [1, 2],
                ],
            ]);
            $query->set('genre', 'rock');

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery)->toHaveCount(3); // existing + new + relation
            expect($taxQuery[0]['taxonomy'])->toBe('existing');
            expect($taxQuery[1]['taxonomy'])->toBe('genre');
        });

        it('skips empty values', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', '');

            $hook->applyFilters($query);

            expect($query->get('tax_query'))->toBe('');
        });

        it('validates public vars against whitelist', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('posts_per_page', 24);

            $hook->applyFilters($query);

            // Valid value should be kept
            expect($query->get('posts_per_page'))->toBe(24);
        });

        it('rejects invalid public var values', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('posts_per_page', 100);

            $hook->applyFilters($query);

            // Invalid value should be reset
            expect($query->get('posts_per_page'))->toBe('');
        });

        it('applies taxonomy filter with array values', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', ['rock', 'jazz']);

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery[0]['terms'])->toBe(['rock', 'jazz']);
        });

        it('skips non-string non-array values', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', 123); // integer value

            $hook->applyFilters($query);

            expect($query->get('tax_query'))->toBe('');
        });

        it('skips empty array values', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['in']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre', []);

            $hook->applyFilters($query);

            expect($query->get('tax_query'))->toBe('');
        });

        it('applies taxonomy filter with EXISTS operator', function () {
            $config = new QueryFiltersConfig(taxonomies: ['genre' => ['exists']]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('genre__exists', '1');

            $hook->applyFilters($query);

            $taxQuery = $query->get('tax_query');
            expect($taxQuery[0]['taxonomy'])->toBe('genre');
            expect($taxQuery[0]['operator'])->toBe('EXISTS');
        });

        it('skips empty public var values', function () {
            $config = new QueryFiltersConfig(publicVars: ['posts_per_page' => [12, 24, 48]]);
            $hook = new QueryFiltersHook($config);

            $query = new WP_Query();
            $query->set('posts_per_page', '');

            $hook->applyFilters($query);

            // Empty value should remain empty (not validated)
            expect($query->get('posts_per_page'))->toBe('');
        });
    });
});
