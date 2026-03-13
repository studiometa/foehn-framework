<?php

declare(strict_types=1);

use App\Models\Product;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Contracts\ConfiguresPostType;
use Studiometa\Foehn\Models\Post;
use Studiometa\Foehn\PostTypes\PostTypeBuilder;

describe('Product', function () {
    it('extends Foehn Post model', function () {
        expect(is_subclass_of(Product::class, Post::class))->toBeTrue();
    });

    it('implements ConfiguresPostType', function () {
        expect(is_subclass_of(Product::class, ConfiguresPostType::class))->toBeTrue();
    });

    it('has AsPostType attribute with correct config', function () {
        $ref = new ReflectionClass(Product::class);
        $attrs = $ref->getAttributes(AsPostType::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('product');
        expect($attr->singular)->toBe('Produit');
        expect($attr->plural)->toBe('Produits');
        expect($attr->public)->toBeTrue();
        expect($attr->hasArchive)->toBeTrue();
        expect($attr->showInRest)->toBeTrue();
        expect($attr->menuIcon)->toBe('dashicons-cart');
        expect($attr->supports)->toContain('title', 'editor', 'thumbnail');
        expect($attr->taxonomies)->toContain('product_category', 'product_tag');
    });

    it('configures rewrite slug and menu position', function () {
        $builder = new PostTypeBuilder('product', 'Produit', 'Produits');
        $result = Product::configurePostType($builder);

        $args = $result->build();

        expect($args['rewrite']['slug'])->toBe('boutique');
        expect($args['rewrite']['with_front'])->toBeFalse();
        expect($args['menu_position'])->toBe(5);
    });
});
