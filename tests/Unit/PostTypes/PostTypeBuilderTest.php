<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsPostType;
use Studiometa\WPTempest\PostTypes\PostTypeBuilder;

describe('PostTypeBuilder', function () {
    it('can be instantiated with a name', function () {
        $builder = new PostTypeBuilder('product');

        expect($builder->getName())->toBe('product');
    });

    it('can be created from an attribute', function () {
        $attribute = new AsPostType(
            name: 'product',
            singular: 'Product',
            plural: 'Products',
            public: true,
            hasArchive: true,
            menuIcon: 'dashicons-cart',
        );

        $builder = PostTypeBuilder::fromAttribute($attribute);

        expect($builder->getName())->toBe('product');

        $args = $builder->build();
        expect($args['labels']['singular_name'])->toBe('Product');
        expect($args['labels']['name'])->toBe('Products');
        expect($args['public'])->toBeTrue();
        expect($args['has_archive'])->toBeTrue();
        expect($args['menu_icon'])->toBe('dashicons-cart');
    });

    it('builds correct args array', function () {
        $builder = new PostTypeBuilder('product');
        $builder
            ->setLabels('Product', 'Products')
            ->setPublic(true)
            ->setHasArchive(true)
            ->setShowInRest(true)
            ->setMenuIcon('dashicons-cart')
            ->setSupports(['title', 'editor'])
            ->setTaxonomies(['category'])
            ->setRewriteSlug('shop');

        $args = $builder->build();

        expect($args['labels']['name'])->toBe('Products');
        expect($args['labels']['singular_name'])->toBe('Product');
        expect($args['public'])->toBeTrue();
        expect($args['has_archive'])->toBeTrue();
        expect($args['show_in_rest'])->toBeTrue();
        expect($args['menu_icon'])->toBe('dashicons-cart');
        expect($args['supports'])->toBe(['title', 'editor']);
        expect($args['taxonomies'])->toBe(['category']);
        expect($args['rewrite'])->toBe(['slug' => 'shop']);
    });

    it('generates all WordPress labels', function () {
        $builder = new PostTypeBuilder('product');
        $builder->setLabels('Product', 'Products');

        $args = $builder->build();
        $labels = $args['labels'];

        expect($labels)->toHaveKeys([
            'name',
            'singular_name',
            'add_new',
            'add_new_item',
            'edit_item',
            'new_item',
            'view_item',
            'view_items',
            'search_items',
            'not_found',
            'not_found_in_trash',
            'all_items',
            'archives',
            'attributes',
            'insert_into_item',
            'uploaded_to_this_item',
            'filter_items_list',
            'items_list_navigation',
            'items_list',
            'item_published',
            'item_published_privately',
            'item_reverted_to_draft',
            'item_scheduled',
            'item_updated',
        ]);
    });

    it('can merge extra args', function () {
        $builder = new PostTypeBuilder('product');
        $builder->setExtraArgs(['capability_type' => 'product'])->mergeExtraArgs(['hierarchical' => false]);

        $args = $builder->build();

        expect($args['capability_type'])->toBe('product');
        expect($args['hierarchical'])->toBeFalse();
    });

    it('supports fluent interface', function () {
        $builder = new PostTypeBuilder('product');

        $result = $builder->setLabels('Product', 'Products')->setPublic(true)->setHasArchive(true);

        expect($result)->toBe($builder);
    });

    it('supports hierarchical post types', function () {
        $attribute = new AsPostType(
            name: 'guide',
            singular: 'Guide',
            plural: 'Guides',
            hierarchical: true,
        );

        $builder = PostTypeBuilder::fromAttribute($attribute);
        $args = $builder->build();

        expect($args['hierarchical'])->toBeTrue();
    });

    it('supports menu position', function () {
        $builder = new PostTypeBuilder('product');
        $builder->setMenuPosition(25);

        $args = $builder->build();

        expect($args['menu_position'])->toBe(25);
    });

    it('supports custom labels merged with auto-generated', function () {
        $attribute = new AsPostType(
            name: 'product',
            singular: 'Product',
            plural: 'Products',
            labels: ['menu_name' => 'Shop', 'add_new' => 'Add Product'],
        );

        $builder = PostTypeBuilder::fromAttribute($attribute);
        $args = $builder->build();

        expect($args['labels']['menu_name'])->toBe('Shop');
        expect($args['labels']['add_new'])->toBe('Add Product');
        // Auto-generated labels still present
        expect($args['labels']['singular_name'])->toBe('Product');
    });

    it('supports full rewrite config', function () {
        $attribute = new AsPostType(
            name: 'product',
            rewrite: ['slug' => 'shop', 'with_front' => false],
        );

        $builder = PostTypeBuilder::fromAttribute($attribute);
        $args = $builder->build();

        expect($args['rewrite'])->toBe(['slug' => 'shop', 'with_front' => false]);
    });

    it('supports rewrite false to disable', function () {
        $attribute = new AsPostType(
            name: 'internal',
            rewrite: false,
        );

        $builder = PostTypeBuilder::fromAttribute($attribute);
        $args = $builder->build();

        expect($args['rewrite'])->toBeFalse();
    });

    it('prioritizes rewrite over rewriteSlug', function () {
        $attribute = new AsPostType(
            name: 'product',
            rewriteSlug: 'old-slug',
            rewrite: ['slug' => 'new-slug', 'with_front' => false],
        );

        $builder = PostTypeBuilder::fromAttribute($attribute);
        $args = $builder->build();

        expect($args['rewrite'])->toBe(['slug' => 'new-slug', 'with_front' => false]);
    });
});
