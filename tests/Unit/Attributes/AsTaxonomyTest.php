<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTaxonomy;

describe('AsTaxonomy', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsTaxonomy(name: 'genre');

        expect($attribute->name)->toBe('genre');
        expect($attribute->postTypes)->toBe([]);
        expect($attribute->singular)->toBeNull();
        expect($attribute->plural)->toBeNull();
        expect($attribute->public)->toBeTrue();
        expect($attribute->hierarchical)->toBeFalse();
        expect($attribute->showInRest)->toBeTrue();
        expect($attribute->showAdminColumn)->toBeTrue();
        expect($attribute->rewriteSlug)->toBeNull();
        expect($attribute->labels)->toBe([]);
        expect($attribute->rewrite)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsTaxonomy(
            name: 'product_category',
            postTypes: ['product'],
            singular: 'Category',
            plural: 'Categories',
            public: true,
            hierarchical: true,
            showInRest: true,
            showAdminColumn: true,
            rewriteSlug: 'category',
            labels: ['menu_name' => 'Product Categories'],
            rewrite: ['slug' => 'product-cat', 'hierarchical' => true],
        );

        expect($attribute->name)->toBe('product_category');
        expect($attribute->postTypes)->toBe(['product']);
        expect($attribute->singular)->toBe('Category');
        expect($attribute->plural)->toBe('Categories');
        expect($attribute->public)->toBeTrue();
        expect($attribute->hierarchical)->toBeTrue();
        expect($attribute->showInRest)->toBeTrue();
        expect($attribute->showAdminColumn)->toBeTrue();
        expect($attribute->rewriteSlug)->toBe('category');
        expect($attribute->labels)->toBe(['menu_name' => 'Product Categories']);
        expect($attribute->rewrite)->toBe(['slug' => 'product-cat', 'hierarchical' => true]);
    });

    it('supports rewrite as false to disable', function () {
        $attribute = new AsTaxonomy(name: 'internal_tag', rewrite: false);

        expect($attribute->rewrite)->toBeFalse();
    });

    it('is readonly', function () {
        expect(AsTaxonomy::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsTaxonomy::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
