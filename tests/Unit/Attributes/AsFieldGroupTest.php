<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsFieldGroup;

describe('AsFieldGroup', function () {
    it('can be instantiated with required parameters only', function () {
        $fieldGroup = new AsFieldGroup(
            key: 'product_fields',
            title: 'Product Fields',
        );

        expect($fieldGroup->key)->toBe('product_fields');
        expect($fieldGroup->title)->toBe('Product Fields');
        expect($fieldGroup->location)->toBe([]);
        expect($fieldGroup->menuOrder)->toBe(0);
        expect($fieldGroup->position)->toBe('normal');
        expect($fieldGroup->style)->toBe('default');
        expect($fieldGroup->labelPlacement)->toBe('top');
        expect($fieldGroup->instructionPlacement)->toBe('label');
        expect($fieldGroup->active)->toBeTrue();
    });

    it('can be instantiated with location rules', function () {
        $location = [['post_type', '==', 'product']];
        $fieldGroup = new AsFieldGroup(
            key: 'product_fields',
            title: 'Product Fields',
            location: $location,
        );

        expect($fieldGroup->location)->toBe($location);
    });

    it('can be instantiated with all parameters', function () {
        $location = [['post_type', '==', 'page']];
        $fieldGroup = new AsFieldGroup(
            key: 'page_fields',
            title: 'Page Fields',
            location: $location,
            menuOrder: 5,
            position: 'side',
            style: 'seamless',
            labelPlacement: 'left',
            instructionPlacement: 'field',
            active: false,
        );

        expect($fieldGroup->key)->toBe('page_fields');
        expect($fieldGroup->title)->toBe('Page Fields');
        expect($fieldGroup->location)->toBe($location);
        expect($fieldGroup->menuOrder)->toBe(5);
        expect($fieldGroup->position)->toBe('side');
        expect($fieldGroup->style)->toBe('seamless');
        expect($fieldGroup->labelPlacement)->toBe('left');
        expect($fieldGroup->instructionPlacement)->toBe('field');
        expect($fieldGroup->active)->toBeFalse();
    });

    it('is readonly', function () {
        expect(AsFieldGroup::class)->toBeReadonly();
    });

    it('can be used as an attribute', function () {
        $reflection = new ReflectionClass(AsFieldGroup::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
    });

    it('targets classes only', function () {
        $reflection = new ReflectionClass(AsFieldGroup::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
