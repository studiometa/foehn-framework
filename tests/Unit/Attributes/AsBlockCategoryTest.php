<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockCategory;

describe('AsBlockCategory', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsBlockCategory(slug: 'my-category', title: 'My Category');

        expect($attribute->slug)->toBe('my-category');
        expect($attribute->title)->toBe('My Category');
        expect($attribute->icon)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsBlockCategory(
            slug: 'custom-blocks',
            title: 'Custom Blocks',
            icon: 'dashicons-admin-customizer',
        );

        expect($attribute->slug)->toBe('custom-blocks');
        expect($attribute->title)->toBe('Custom Blocks');
        expect($attribute->icon)->toBe('dashicons-admin-customizer');
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsBlockCategory::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $flags = $attributes[0]->newInstance()->flags;

        expect($flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });

    it('is repeatable', function () {
        $reflection = new ReflectionClass(AsBlockCategory::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        $flags = $attributes[0]->newInstance()->flags;

        expect($flags & Attribute::IS_REPEATABLE)->toBeTruthy();
    });

    it('is readonly', function () {
        $reflection = new ReflectionClass(AsBlockCategory::class);

        expect($reflection->isReadonly())->toBeTrue();
    });

    it('can be used as an attribute', function () {
        #[AsBlockCategory('theme-blocks', 'Theme Blocks')]
        #[AsBlockCategory('layout-blocks', 'Layout Blocks', 'dashicons-layout')]
        class BlockCategoryTestClass {}

        $reflection = new ReflectionClass(BlockCategoryTestClass::class);
        $attributes = $reflection->getAttributes(AsBlockCategory::class);

        expect($attributes)->toHaveCount(2);

        $first = $attributes[0]->newInstance();
        $second = $attributes[1]->newInstance();

        expect($first->slug)->toBe('theme-blocks');
        expect($first->title)->toBe('Theme Blocks');
        expect($first->icon)->toBeNull();

        expect($second->slug)->toBe('layout-blocks');
        expect($second->title)->toBe('Layout Blocks');
        expect($second->icon)->toBe('dashicons-layout');
    });
});
