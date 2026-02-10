<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockPattern;

describe('AsBlockPattern', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsBlockPattern(name: 'theme/hero', title: 'Hero Pattern');

        expect($attribute->name)->toBe('theme/hero');
        expect($attribute->title)->toBe('Hero Pattern');
        expect($attribute->categories)->toBe([]);
        expect($attribute->keywords)->toBe([]);
        expect($attribute->blockTypes)->toBe([]);
        expect($attribute->description)->toBeNull();
        expect($attribute->template)->toBeNull();
        expect($attribute->viewportWidth)->toBe(1200);
        expect($attribute->inserter)->toBeTrue();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsBlockPattern(
            name: 'theme/hero-with-cta',
            title: 'Hero with CTA',
            categories: ['hero', 'call-to-action'],
            keywords: ['banner', 'header'],
            blockTypes: ['core/cover'],
            description: 'A hero section with call to action',
            template: 'patterns/custom-hero',
            viewportWidth: 1400,
            inserter: false,
        );

        expect($attribute->name)->toBe('theme/hero-with-cta');
        expect($attribute->title)->toBe('Hero with CTA');
        expect($attribute->categories)->toBe(['hero', 'call-to-action']);
        expect($attribute->keywords)->toBe(['banner', 'header']);
        expect($attribute->blockTypes)->toBe(['core/cover']);
        expect($attribute->description)->toBe('A hero section with call to action');
        expect($attribute->template)->toBe('patterns/custom-hero');
        expect($attribute->viewportWidth)->toBe(1400);
        expect($attribute->inserter)->toBeFalse();
    });

    it('auto-resolves template path from name', function () {
        $attribute = new AsBlockPattern(name: 'theme/hero-with-cta', title: 'Hero');

        expect($attribute->getTemplatePath())->toBe('patterns/hero-with-cta');
    });

    it('uses custom template when provided', function () {
        $attribute = new AsBlockPattern(name: 'theme/hero', title: 'Hero', template: 'custom/path/hero');

        expect($attribute->getTemplatePath())->toBe('custom/path/hero');
    });

    it('is readonly', function () {
        expect(AsBlockPattern::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsBlockPattern::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
