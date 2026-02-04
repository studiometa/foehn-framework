<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsShortcode;

describe('AsShortcode', function () {
    it('can be instantiated with tag', function () {
        $attribute = new AsShortcode(tag: 'gallery');

        expect($attribute->tag)->toBe('gallery');
    });

    it('is readonly', function () {
        expect(AsShortcode::class)->toBeReadonly();
    });

    it('is a method attribute', function () {
        $reflection = new ReflectionClass(AsShortcode::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_METHOD)->toBeTruthy();
    });
});
