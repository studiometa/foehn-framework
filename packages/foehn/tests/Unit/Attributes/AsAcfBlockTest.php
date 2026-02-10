<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAcfBlock;

describe('AsAcfBlock', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsAcfBlock(name: 'hero', title: 'Hero Block');

        expect($attribute->name)->toBe('hero');
        expect($attribute->title)->toBe('Hero Block');
        expect($attribute->category)->toBe('common');
        expect($attribute->icon)->toBeNull();
        expect($attribute->description)->toBeNull();
        expect($attribute->keywords)->toBe([]);
        expect($attribute->mode)->toBe('preview');
        expect($attribute->supports)->toBe([]);
        expect($attribute->template)->toBeNull();
        expect($attribute->postTypes)->toBe([]);
        expect($attribute->parent)->toBeNull();
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsAcfBlock(
            name: 'hero',
            title: 'Hero Block',
            category: 'layout',
            icon: 'cover-image',
            description: 'A hero banner block',
            keywords: ['banner', 'header'],
            mode: 'edit',
            supports: ['align' => true, 'mode' => false],
            template: 'blocks/hero',
            postTypes: ['page'],
            parent: 'acf/section',
        );

        expect($attribute->name)->toBe('hero');
        expect($attribute->title)->toBe('Hero Block');
        expect($attribute->category)->toBe('layout');
        expect($attribute->icon)->toBe('cover-image');
        expect($attribute->description)->toBe('A hero banner block');
        expect($attribute->keywords)->toBe(['banner', 'header']);
        expect($attribute->mode)->toBe('edit');
        expect($attribute->supports)->toBe(['align' => true, 'mode' => false]);
        expect($attribute->template)->toBe('blocks/hero');
        expect($attribute->postTypes)->toBe(['page']);
        expect($attribute->parent)->toBe('acf/section');
    });

    it('returns full block name with acf prefix', function () {
        $attribute = new AsAcfBlock(name: 'hero', title: 'Hero');

        expect($attribute->getFullName())->toBe('acf/hero');
    });

    it('is readonly', function () {
        expect(AsAcfBlock::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $reflection = new ReflectionClass(AsAcfBlock::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
