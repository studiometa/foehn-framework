<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTwigExtension;

describe('AsTwigExtension', function () {
    it('can be instantiated with defaults', function () {
        $attribute = new AsTwigExtension();

        expect($attribute->priority)->toBe(10);
    });

    it('can be instantiated with custom priority', function () {
        $attribute = new AsTwigExtension(priority: 5);

        expect($attribute->priority)->toBe(5);
    });

    it('is readonly', function () {
        expect(AsTwigExtension::class)->toBeReadonly();
    });

    it('can be used as an attribute', function () {
        $reflection = new ReflectionClass(AsTwigExtension::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
    });

    it('targets classes only', function () {
        $reflection = new ReflectionClass(AsTwigExtension::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
