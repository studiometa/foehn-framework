<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsPostType;

describe('AsPostType', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsPostType(name: 'product');

        expect($attribute->name)->toBe('product');
        expect($attribute->singular)->toBeNull();
        expect($attribute->plural)->toBeNull();
        expect($attribute->public)->toBeTrue();
        expect($attribute->hasArchive)->toBeFalse();
        expect($attribute->showInRest)->toBeTrue();
        expect($attribute->menuIcon)->toBeNull();
        expect($attribute->supports)->toBe(['title', 'editor', 'thumbnail']);
        expect($attribute->taxonomies)->toBe([]);
        expect($attribute->rewriteSlug)->toBeNull();
        expect($attribute->hierarchical)->toBeFalse();
        expect($attribute->menuPosition)->toBeNull();
        expect($attribute->labels)->toBe([]);
        expect($attribute->rewrite)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsPostType(
            name: 'product',
            singular: 'Product',
            plural: 'Products',
            public: true,
            hasArchive: true,
            showInRest: true,
            menuIcon: 'dashicons-cart',
            supports: ['title', 'editor', 'thumbnail', 'excerpt'],
            taxonomies: ['product_category'],
            rewriteSlug: 'shop',
            hierarchical: true,
            menuPosition: 25,
            labels: ['menu_name' => 'Shop'],
            rewrite: ['slug' => 'shop', 'with_front' => false],
        );

        expect($attribute->name)->toBe('product');
        expect($attribute->singular)->toBe('Product');
        expect($attribute->plural)->toBe('Products');
        expect($attribute->public)->toBeTrue();
        expect($attribute->hasArchive)->toBeTrue();
        expect($attribute->showInRest)->toBeTrue();
        expect($attribute->menuIcon)->toBe('dashicons-cart');
        expect($attribute->supports)->toBe(['title', 'editor', 'thumbnail', 'excerpt']);
        expect($attribute->taxonomies)->toBe(['product_category']);
        expect($attribute->rewriteSlug)->toBe('shop');
        expect($attribute->hierarchical)->toBeTrue();
        expect($attribute->menuPosition)->toBe(25);
        expect($attribute->labels)->toBe(['menu_name' => 'Shop']);
        expect($attribute->rewrite)->toBe(['slug' => 'shop', 'with_front' => false]);
    });

    it('supports rewrite as false to disable', function () {
        $attribute = new AsPostType(
            name: 'internal',
            rewrite: false,
        );

        expect($attribute->rewrite)->toBeFalse();
    });

    it('is readonly', function () {
        expect(AsPostType::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsPostType::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
