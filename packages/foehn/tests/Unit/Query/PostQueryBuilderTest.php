<?php

declare(strict_types=1);

use Studiometa\Foehn\Query\PostQueryBuilder;

describe('PostQueryBuilder', function () {
    describe('initialization', function () {
        it('sets post_type and default status', function () {
            $builder = new PostQueryBuilder('product');

            $params = $builder->getParameters();

            expect($params['post_type'])->toBe('product');
            expect($params['post_status'])->toBe('publish');
        });
    });

    describe('pagination', function () {
        it('sets limit via posts_per_page', function () {
            $builder = new PostQueryBuilder('product');
            $builder->limit(10);

            expect($builder->getParameters()['posts_per_page'])->toBe(10);
        });

        it('sets offset', function () {
            $builder = new PostQueryBuilder('product');
            $builder->offset(5);

            expect($builder->getParameters()['offset'])->toBe(5);
        });

        it('sets page via paged', function () {
            $builder = new PostQueryBuilder('product');
            $builder->page(3);

            expect($builder->getParameters()['paged'])->toBe(3);
        });

        it('ignores page when <= 0', function () {
            $builder = new PostQueryBuilder('product');
            $builder->page(0);

            expect($builder->getParameters())->not->toHaveKey('paged');

            $builder->page(-1);

            expect($builder->getParameters())->not->toHaveKey('paged');
        });
    });

    describe('ordering', function () {
        it('sets orderby and order', function () {
            $builder = new PostQueryBuilder('product');
            $builder->orderBy('title', 'ASC');

            $params = $builder->getParameters();

            expect($params['orderby'])->toBe('title');
            expect($params['order'])->toBe('ASC');
        });

        it('defaults to DESC order', function () {
            $builder = new PostQueryBuilder('product');
            $builder->orderBy('date');

            expect($builder->getParameters()['order'])->toBe('DESC');
        });

        it('sets orderByMeta with string type', function () {
            $builder = new PostQueryBuilder('product');
            $builder->orderByMeta('sku', 'ASC');

            $params = $builder->getParameters();

            expect($params['meta_key'])->toBe('sku');
            expect($params['orderby'])->toBe('meta_value');
            expect($params['order'])->toBe('ASC');
        });

        it('sets orderByMeta with numeric type', function () {
            $builder = new PostQueryBuilder('product');
            $builder->orderByMeta('price', 'ASC', numeric: true);

            $params = $builder->getParameters();

            expect($params['meta_key'])->toBe('price');
            expect($params['orderby'])->toBe('meta_value_num');
        });
    });

    describe('status filtering', function () {
        it('sets single status', function () {
            $builder = new PostQueryBuilder('product');
            $builder->status('draft');

            expect($builder->getParameters()['post_status'])->toBe('draft');
        });

        it('sets multiple statuses', function () {
            $builder = new PostQueryBuilder('product');
            $builder->status(['publish', 'draft']);

            expect($builder->getParameters()['post_status'])->toBe(['publish', 'draft']);
        });
    });

    describe('ID filtering', function () {
        it('includes specific IDs', function () {
            $builder = new PostQueryBuilder('product');
            $builder->include(1, 2, 3);

            expect($builder->getParameters()['post__in'])->toBe([1, 2, 3]);
        });

        it('accumulates included IDs', function () {
            $builder = new PostQueryBuilder('product');
            $builder->include(1, 2)->include(3);

            expect($builder->getParameters()['post__in'])->toBe([1, 2, 3]);
        });

        it('ignores empty include', function () {
            $builder = new PostQueryBuilder('product');
            $builder->include();

            expect($builder->getParameters())->not->toHaveKey('post__in');
        });

        it('excludes specific IDs', function () {
            $builder = new PostQueryBuilder('product');
            $builder->exclude(1, 2, 3);

            expect($builder->getParameters()['post__not_in'])->toBe([1, 2, 3]);
        });

        it('accumulates excluded IDs', function () {
            $builder = new PostQueryBuilder('product');
            $builder->exclude(1, 2)->exclude(3);

            expect($builder->getParameters()['post__not_in'])->toBe([1, 2, 3]);
        });

        it('ignores empty exclude', function () {
            $builder = new PostQueryBuilder('product');
            $builder->exclude();

            expect($builder->getParameters())->not->toHaveKey('post__not_in');
        });
    });

    describe('taxonomy filtering', function () {
        it('adds tax_query clause', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereTax('category', 'featured');

            $params = $builder->getParameters();

            expect($params['tax_query'])->toBeArray();
            expect($params['tax_query'][0])->toBe([
                'taxonomy' => 'category',
                'field' => 'slug',
                'terms' => 'featured',
                'operator' => 'IN',
            ]);
        });

        it('supports custom field and operator', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereTax('category', 5, 'term_id', 'NOT IN');

            $params = $builder->getParameters();

            expect($params['tax_query'][0]['field'])->toBe('term_id');
            expect($params['tax_query'][0]['terms'])->toBe(5);
            expect($params['tax_query'][0]['operator'])->toBe('NOT IN');
        });

        it('stacks multiple tax_query clauses', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereTax('category', 'featured')->whereTax('tag', 'sale');

            $params = $builder->getParameters();

            expect($params['tax_query'])->toHaveCount(2);
        });

        it('ignores null terms', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereTax('category', null);

            expect($builder->getParameters())->not->toHaveKey('tax_query');
        });

        it('ignores empty string terms', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereTax('category', '');

            expect($builder->getParameters())->not->toHaveKey('tax_query');
        });

        it('ignores empty array terms', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereTax('category', []);

            expect($builder->getParameters())->not->toHaveKey('tax_query');
        });

        it('sets tax_query relation', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereTax('category', 'a')->whereTax('tag', 'b')->taxRelation('OR');

            expect($builder->getParameters()['tax_query']['relation'])->toBe('OR');
        });
    });

    describe('meta filtering', function () {
        it('adds meta_query clause', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereMeta('featured', '1');

            $params = $builder->getParameters();

            expect($params['meta_query'])->toBeArray();
            expect($params['meta_query'][0])->toBe([
                'key' => 'featured',
                'value' => '1',
                'compare' => '=',
            ]);
        });

        it('supports compare and type', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereMeta('price', 100, '>', 'NUMERIC');

            $params = $builder->getParameters();

            expect($params['meta_query'][0])->toBe([
                'key' => 'price',
                'value' => 100,
                'compare' => '>',
                'type' => 'NUMERIC',
            ]);
        });

        it('stacks multiple meta_query clauses', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereMeta('featured', '1')->whereMeta('in_stock', '1');

            expect($builder->getParameters()['meta_query'])->toHaveCount(2);
        });

        it('sets meta_query relation', function () {
            $builder = new PostQueryBuilder('product');
            $builder->whereMeta('a', '1')->whereMeta('b', '1')->metaRelation('OR');

            expect($builder->getParameters()['meta_query']['relation'])->toBe('OR');
        });
    });

    describe('search filtering', function () {
        it('sets search terms', function () {
            $builder = new PostQueryBuilder('product');
            $builder->search('keyword');

            expect($builder->getParameters()['s'])->toBe('keyword');
        });

        it('ignores empty search', function () {
            $builder = new PostQueryBuilder('product');
            $builder->search('');

            expect($builder->getParameters())->not->toHaveKey('s');
        });
    });

    describe('author filtering', function () {
        it('sets author ID', function () {
            $builder = new PostQueryBuilder('product');
            $builder->byAuthor(5);

            expect($builder->getParameters()['author'])->toBe(5);
        });
    });

    describe('date filtering', function () {
        it('sets date_query', function () {
            $builder = new PostQueryBuilder('product');
            $builder->dateQuery([
                ['after' => '2024-01-01', 'inclusive' => true],
            ]);

            expect($builder->getParameters()['date_query'])->toBe([
                ['after' => '2024-01-01', 'inclusive' => true],
            ]);
        });
    });

    describe('parent filtering', function () {
        it('sets parent', function () {
            $builder = new PostQueryBuilder('page');
            $builder->parent(0);

            expect($builder->getParameters()['post_parent'])->toBe(0);
        });

        it('sets parent__in', function () {
            $builder = new PostQueryBuilder('page');
            $builder->parentIn(1, 2, 3);

            expect($builder->getParameters()['post_parent__in'])->toBe([1, 2, 3]);
        });

        it('ignores empty parentIn', function () {
            $builder = new PostQueryBuilder('page');
            $builder->parentIn();

            expect($builder->getParameters())->not->toHaveKey('post_parent__in');
        });

        it('sets parent__not_in', function () {
            $builder = new PostQueryBuilder('page');
            $builder->parentNotIn(1, 2, 3);

            expect($builder->getParameters()['post_parent__not_in'])->toBe([1, 2, 3]);
        });

        it('ignores empty parentNotIn', function () {
            $builder = new PostQueryBuilder('page');
            $builder->parentNotIn();

            expect($builder->getParameters())->not->toHaveKey('post_parent__not_in');
        });
    });

    describe('escape hatch', function () {
        it('sets arbitrary parameter via set()', function () {
            $builder = new PostQueryBuilder('product');
            $builder->set('fields', 'ids');

            expect($builder->getParameters()['fields'])->toBe('ids');
        });

        it('merges parameters', function () {
            $builder = new PostQueryBuilder('product');
            $builder->merge([
                'meta_key' => 'price',
                'orderby' => 'meta_value_num',
            ]);

            $params = $builder->getParameters();

            expect($params['meta_key'])->toBe('price');
            expect($params['orderby'])->toBe('meta_value_num');
        });

        it('merge overwrites existing params', function () {
            $builder = new PostQueryBuilder('product');
            $builder->limit(10)->merge(['posts_per_page' => 20]);

            expect($builder->getParameters()['posts_per_page'])->toBe(20);
        });
    });

    describe('fluent interface', function () {
        it('returns self from all builder methods', function () {
            $builder = new PostQueryBuilder('product');

            expect($builder->limit(10))->toBe($builder);
            expect($builder->offset(5))->toBe($builder);
            expect($builder->page(1))->toBe($builder);
            expect($builder->orderBy('date'))->toBe($builder);
            expect($builder->orderByMeta('price'))->toBe($builder);
            expect($builder->status('publish'))->toBe($builder);
            expect($builder->include(1))->toBe($builder);
            expect($builder->exclude(1))->toBe($builder);
            expect($builder->whereTax('cat', 'test'))->toBe($builder);
            expect($builder->taxRelation('AND'))->toBe($builder);
            expect($builder->whereMeta('key', 'val'))->toBe($builder);
            expect($builder->metaRelation('AND'))->toBe($builder);
            expect($builder->search('test'))->toBe($builder);
            expect($builder->byAuthor(1))->toBe($builder);
            expect($builder->dateQuery([]))->toBe($builder);
            expect($builder->parent(0))->toBe($builder);
            expect($builder->parentIn(1))->toBe($builder);
            expect($builder->parentNotIn(1))->toBe($builder);
            expect($builder->set('key', 'val'))->toBe($builder);
            expect($builder->merge([]))->toBe($builder);
        });
    });

    describe('complex queries', function () {
        it('builds multi-taxonomy filter', function () {
            $builder = new PostQueryBuilder('event');
            $builder
                ->limit(10)
                ->whereTax('year', '2024')
                ->whereTax('event_category', 'conference')
                ->taxRelation('AND')
                ->orderBy('date')
                ->exclude(1, 2, 3)
                ->page(2);

            $params = $builder->getParameters();

            expect($params['post_type'])->toBe('event');
            expect($params['posts_per_page'])->toBe(10);
            expect($params['tax_query'])->toHaveCount(3); // 2 clauses + relation
            expect($params['tax_query']['relation'])->toBe('AND');
            expect($params['orderby'])->toBe('date');
            expect($params['post__not_in'])->toBe([1, 2, 3]);
            expect($params['paged'])->toBe(2);
        });

        it('builds meta + taxonomy filter with escape hatch', function () {
            $builder = new PostQueryBuilder('product');
            $builder
                ->limit(20)
                ->whereTax('product_category', 'electronics')
                ->whereMeta('price', 100, '>=', 'NUMERIC')
                ->whereMeta('in_stock', '1')
                ->metaRelation('AND')
                ->set('suppress_filters', false)
                ->merge([
                    'date_query' => [
                        ['after' => '2024-01-01', 'inclusive' => true],
                    ],
                ]);

            $params = $builder->getParameters();

            expect($params['tax_query'])->toHaveCount(1);
            expect($params['meta_query'])->toHaveCount(3); // 2 clauses + relation
            expect($params['meta_query']['relation'])->toBe('AND');
            expect($params['suppress_filters'])->toBeFalse();
            expect($params['date_query'])->toBeArray();
        });
    });
});
