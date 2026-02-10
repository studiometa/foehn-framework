<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlock;

describe('AsBlock', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsBlock(name: 'theme/counter', title: 'Counter');

        expect($attribute->name)->toBe('theme/counter');
        expect($attribute->title)->toBe('Counter');
        expect($attribute->category)->toBe('widgets');
        expect($attribute->icon)->toBeNull();
        expect($attribute->description)->toBeNull();
        expect($attribute->keywords)->toBe([]);
        expect($attribute->supports)->toBe([]);
        expect($attribute->parent)->toBeNull();
        expect($attribute->ancestor)->toBe([]);
        expect($attribute->interactivity)->toBeFalse();
        expect($attribute->interactivityNamespace)->toBeNull();
        expect($attribute->template)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsBlock(
            name: 'theme/counter',
            title: 'Counter',
            category: 'widgets',
            icon: 'plus-alt',
            description: 'An interactive counter block',
            keywords: ['counter', 'number'],
            supports: ['align' => true],
            parent: 'theme/section',
            ancestor: ['theme/layout'],
            interactivity: true,
            interactivityNamespace: 'custom/namespace',
            template: 'blocks/counter',
            editorScript: 'file:./editor.js',
            editorStyle: 'file:./editor.css',
            style: 'file:./style.css',
            viewScript: 'file:./view.js',
        );

        expect($attribute->name)->toBe('theme/counter');
        expect($attribute->title)->toBe('Counter');
        expect($attribute->category)->toBe('widgets');
        expect($attribute->icon)->toBe('plus-alt');
        expect($attribute->description)->toBe('An interactive counter block');
        expect($attribute->keywords)->toBe(['counter', 'number']);
        expect($attribute->supports)->toBe(['align' => true]);
        expect($attribute->parent)->toBe('theme/section');
        expect($attribute->ancestor)->toBe(['theme/layout']);
        expect($attribute->interactivity)->toBeTrue();
        expect($attribute->interactivityNamespace)->toBe('custom/namespace');
        expect($attribute->template)->toBe('blocks/counter');
        expect($attribute->editorScript)->toBe('file:./editor.js');
        expect($attribute->editorStyle)->toBe('file:./editor.css');
        expect($attribute->style)->toBe('file:./style.css');
        expect($attribute->viewScript)->toBe('file:./view.js');
    });

    it('returns block name as interactivity namespace by default', function () {
        $attribute = new AsBlock(name: 'theme/counter', title: 'Counter', interactivity: true);

        expect($attribute->getInteractivityNamespace())->toBe('theme/counter');
    });

    it('returns custom interactivity namespace when set', function () {
        $attribute = new AsBlock(
            name: 'theme/counter',
            title: 'Counter',
            interactivity: true,
            interactivityNamespace: 'custom/namespace',
        );

        expect($attribute->getInteractivityNamespace())->toBe('custom/namespace');
    });

    it('is readonly', function () {
        expect(AsBlock::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsBlock::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
