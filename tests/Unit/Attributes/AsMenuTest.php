<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsMenu;

describe('AsMenu', function () {
    it('can be instantiated with required parameters', function () {
        $attribute = new AsMenu(
            location: 'primary',
            description: 'Primary Navigation',
        );

        expect($attribute->location)->toBe('primary');
        expect($attribute->description)->toBe('Primary Navigation');
    });

    it('is readonly', function () {
        expect(AsMenu::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsMenu::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
