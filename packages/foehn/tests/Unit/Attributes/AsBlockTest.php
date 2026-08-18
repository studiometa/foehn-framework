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
        expect($attribute->allowedBlocks)->toBe([]);
        expect($attribute->innerBlocksTemplate)->toBe([]);
        expect($attribute->innerBlocksTemplateLock)->toBeNull();
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
            allowedBlocks: ['core/paragraph'],
            innerBlocksTemplate: [['core/paragraph', ['placeholder' => 'Text']]],
            innerBlocksTemplateLock: 'insert',
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
        expect($attribute->allowedBlocks)->toBe(['core/paragraph']);
        expect($attribute->innerBlocksTemplate)->toBe([['core/paragraph', ['placeholder' => 'Text']]]);
        expect($attribute->innerBlocksTemplateLock)->toBe('insert');
    });

    it('has no inner blocks by default', function () {
        expect(AsBlock::hasInnerBlocks([], [], null))->toBeFalse();
    });

    it('has inner blocks when allowedBlocks is set', function () {
        expect(AsBlock::hasInnerBlocks(['core/paragraph'], [], null))->toBeTrue();
    });

    it('has inner blocks when innerBlocksTemplate is set', function () {
        expect(AsBlock::hasInnerBlocks([], [['core/paragraph', []]], null))->toBeTrue();
    });

    it('has inner blocks when innerBlocksTemplateLock is set', function () {
        // `false` is an explicit unlock, not an absent value.
        expect(AsBlock::hasInnerBlocks([], [], 'all'))->toBeTrue();
        expect(AsBlock::hasInnerBlocks([], [], false))->toBeTrue();
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
