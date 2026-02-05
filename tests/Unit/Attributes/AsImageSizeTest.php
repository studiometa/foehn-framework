<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsImageSize;

describe('AsImageSize', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsImageSize(width: 800);

        expect($attribute->width)->toBe(800);
        expect($attribute->height)->toBe(0);
        expect($attribute->crop)->toBeFalse();
        expect($attribute->name)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsImageSize(
            width: 1200,
            height: 630,
            crop: true,
            name: 'social_image',
        );

        expect($attribute->width)->toBe(1200);
        expect($attribute->height)->toBe(630);
        expect($attribute->crop)->toBeTrue();
        expect($attribute->name)->toBe('social_image');
    });

    it('can be instantiated with width and height only', function () {
        $attribute = new AsImageSize(width: 1920, height: 1080);

        expect($attribute->width)->toBe(1920);
        expect($attribute->height)->toBe(1080);
        expect($attribute->crop)->toBeFalse();
        expect($attribute->name)->toBeNull();
    });

    it('is readonly', function () {
        expect(AsImageSize::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsImageSize::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
