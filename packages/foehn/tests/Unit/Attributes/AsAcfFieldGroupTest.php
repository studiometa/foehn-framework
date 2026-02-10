<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfFieldGroup;

describe('AsAcfFieldGroup', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsAcfFieldGroup(
            name: 'product_fields',
            title: 'Product Fields',
            location: ['post_type' => 'product'],
        );

        expect($attribute->name)->toBe('product_fields');
        expect($attribute->title)->toBe('Product Fields');
        expect($attribute->location)->toBe(['post_type' => 'product']);
        expect($attribute->position)->toBe('normal');
        expect($attribute->menuOrder)->toBe(0);
        expect($attribute->style)->toBe('default');
        expect($attribute->labelPlacement)->toBe('top');
        expect($attribute->instructionPlacement)->toBe('label');
        expect($attribute->hideOnScreen)->toBe([]);
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsAcfFieldGroup(
            name: 'property_fields',
            title: 'Property Details',
            location: ['post_type' => 'property'],
            position: 'acf_after_title',
            menuOrder: 10,
            style: 'seamless',
            labelPlacement: 'left',
            instructionPlacement: 'field',
            hideOnScreen: ['the_content', 'excerpt'],
        );

        expect($attribute->name)->toBe('property_fields');
        expect($attribute->title)->toBe('Property Details');
        expect($attribute->location)->toBe(['post_type' => 'property']);
        expect($attribute->position)->toBe('acf_after_title');
        expect($attribute->menuOrder)->toBe(10);
        expect($attribute->style)->toBe('seamless');
        expect($attribute->labelPlacement)->toBe('left');
        expect($attribute->instructionPlacement)->toBe('field');
        expect($attribute->hideOnScreen)->toBe(['the_content', 'excerpt']);
    });

    it('supports simplified location syntax', function () {
        $attribute = new AsAcfFieldGroup(
            name: 'page_fields',
            title: 'Page Fields',
            location: ['page_template' => 'page-faq.php'],
        );

        expect($attribute->location)->toBe(['page_template' => 'page-faq.php']);
    });

    it('supports full ACF location syntax', function () {
        $location = [
            [
                ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
                ['param' => 'post_status', 'operator' => '!=', 'value' => 'draft'],
            ],
            [
                ['param' => 'page_template', 'operator' => '==', 'value' => 'page-shop.php'],
            ],
        ];

        $attribute = new AsAcfFieldGroup(
            name: 'complex_fields',
            title: 'Complex Fields',
            location: $location,
        );

        expect($attribute->location)->toBe($location);
    });

    it('is readonly', function () {
        expect(AsAcfFieldGroup::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsAcfFieldGroup::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
