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
