<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsTemplateController;

describe('AsTemplateController', function () {
    it('can be instantiated with a single template', function () {
        $attribute = new AsTemplateController(templates: 'single');

        expect($attribute->templates)->toBe('single');
        expect($attribute->priority)->toBe(10);
        expect($attribute->getTemplates())->toBe(['single']);
    });

    it('can be instantiated with multiple templates', function () {
        $attribute = new AsTemplateController(templates: ['home', 'front-page']);

        expect($attribute->templates)->toBe(['home', 'front-page']);
        expect($attribute->getTemplates())->toBe(['home', 'front-page']);
    });

    it('can be instantiated with custom priority', function () {
        $attribute = new AsTemplateController(templates: 'page', priority: 5);

        expect($attribute->priority)->toBe(5);
    });

    it('supports wildcard patterns', function () {
        $attribute = new AsTemplateController(templates: ['single-*', 'page-*']);

        expect($attribute->getTemplates())->toBe(['single-*', 'page-*']);
    });

    it('is readonly', function () {
        expect(AsTemplateController::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsTemplateController::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
