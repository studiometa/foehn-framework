<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsViewComposer;

describe('AsViewComposer', function () {
    it('can be instantiated with a single template', function () {
        $attribute = new AsViewComposer(templates: 'single');

        expect($attribute->templates)->toBe('single');
        expect($attribute->priority)->toBe(10);
        expect($attribute->getTemplates())->toBe(['single']);
    });

    it('can be instantiated with multiple templates', function () {
        $attribute = new AsViewComposer(templates: ['single', 'page', 'archive']);

        expect($attribute->templates)->toBe(['single', 'page', 'archive']);
        expect($attribute->getTemplates())->toBe(['single', 'page', 'archive']);
    });

    it('can be instantiated with custom priority', function () {
        $attribute = new AsViewComposer(templates: 'single', priority: 5);

        expect($attribute->priority)->toBe(5);
    });

    it('supports wildcard patterns', function () {
        $attribute = new AsViewComposer(templates: ['single-*', 'archive-*']);

        expect($attribute->getTemplates())->toBe(['single-*', 'archive-*']);
    });

    it('is readonly', function () {
        expect(AsViewComposer::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsViewComposer::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
