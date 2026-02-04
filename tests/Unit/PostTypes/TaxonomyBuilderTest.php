<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsTaxonomy;
use Studiometa\WPTempest\PostTypes\TaxonomyBuilder;

describe('TaxonomyBuilder', function () {
    it('can be instantiated with a name', function () {
        $builder = new TaxonomyBuilder('genre');

        expect($builder->getName())->toBe('genre');
        expect($builder->getPostTypes())->toBe([]);
    });

    it('can be created from an attribute', function () {
        $attribute = new AsTaxonomy(
            name: 'product_category',
            postTypes: ['product'],
            singular: 'Category',
            plural: 'Categories',
            hierarchical: true,
        );

        $builder = TaxonomyBuilder::fromAttribute($attribute);

        expect($builder->getName())->toBe('product_category');
        expect($builder->getPostTypes())->toBe(['product']);

        $args = $builder->build();
        expect($args['labels']['singular_name'])->toBe('Category');
        expect($args['labels']['name'])->toBe('Categories');
        expect($args['hierarchical'])->toBeTrue();
    });

    it('builds correct args array', function () {
        $builder = new TaxonomyBuilder('genre');
        $builder
            ->setLabels('Genre', 'Genres')
            ->setPostTypes(['post', 'movie'])
            ->setPublic(true)
            ->setHierarchical(false)
            ->setShowInRest(true)
            ->setShowAdminColumn(true)
            ->setRewriteSlug('genres');

        $args = $builder->build();

        expect($args['labels']['name'])->toBe('Genres');
        expect($args['labels']['singular_name'])->toBe('Genre');
        expect($args['public'])->toBeTrue();
        expect($args['hierarchical'])->toBeFalse();
        expect($args['show_in_rest'])->toBeTrue();
        expect($args['show_admin_column'])->toBeTrue();
        expect($args['rewrite'])->toBe(['slug' => 'genres']);
    });

    it('generates all WordPress labels for non-hierarchical taxonomy', function () {
        $builder = new TaxonomyBuilder('tag');
        $builder->setLabels('Tag', 'Tags')->setHierarchical(false);

        $args = $builder->build();
        $labels = $args['labels'];

        expect($labels)->toHaveKeys([
            'name',
            'singular_name',
            'search_items',
            'popular_items',
            'all_items',
            'edit_item',
            'update_item',
            'add_new_item',
            'new_item_name',
            'separate_items_with_commas',
            'add_or_remove_items',
            'choose_from_most_used',
            'not_found',
            'no_terms',
            'filter_by_item',
            'items_list_navigation',
            'items_list',
            'back_to_items',
            'item_link',
            'item_link_description',
        ]);

        // Non-hierarchical should NOT have parent labels
        expect($labels)->not->toHaveKey('parent_item');
        expect($labels)->not->toHaveKey('parent_item_colon');
    });

    it('generates parent labels for hierarchical taxonomy', function () {
        $builder = new TaxonomyBuilder('category');
        $builder->setLabels('Category', 'Categories')->setHierarchical(true);

        $args = $builder->build();
        $labels = $args['labels'];

        expect($labels)->toHaveKey('parent_item');
        expect($labels)->toHaveKey('parent_item_colon');
        expect($labels['parent_item'])->toBe('Parent Category');
    });

    it('can merge extra args', function () {
        $builder = new TaxonomyBuilder('genre');
        $builder->setExtraArgs(['capabilities' => ['manage_terms' => 'manage_genres']])->mergeExtraArgs([
            'query_var' => 'genre',
        ]);

        $args = $builder->build();

        expect($args['capabilities'])->toBe(['manage_terms' => 'manage_genres']);
        expect($args['query_var'])->toBe('genre');
    });

    it('supports fluent interface', function () {
        $builder = new TaxonomyBuilder('genre');

        $result = $builder->setLabels('Genre', 'Genres')->setPostTypes(['post'])->setHierarchical(false);

        expect($result)->toBe($builder);
    });
});
