<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsSeeder;

describe('AsSeeder', function () {
    it('has defaults', function () {
        $attribute = new AsSeeder();

        expect($attribute->order)->toBe(10);
        expect($attribute->description)->toBe('');
    });

    it('takes an order and a description', function () {
        $attribute = new AsSeeder(order: 20, description: 'Sample events');

        expect($attribute->order)->toBe(20);
        expect($attribute->description)->toBe('Sample events');
    });

    it('is readonly', function () {
        expect(AsSeeder::class)->toBeReadonlyClass();
    });

    it('is a class attribute', function () {
        $attributes = new ReflectionClass(AsSeeder::class)->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
