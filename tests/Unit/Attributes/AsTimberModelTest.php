<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsTimberModel;

describe('AsTimberModel', function () {
    it('can be instantiated with a name', function () {
        $attribute = new AsTimberModel(name: 'post');

        expect($attribute->name)->toBe('post');
    });

    it('is readonly', function () {
        expect(AsTimberModel::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsTimberModel::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
