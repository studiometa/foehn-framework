<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsBlockBinding;

describe('AsBlockBinding', function () {
    it('needs a name and a label', function () {
        $attribute = new AsBlockBinding(name: 'theme/reading-time', label: 'Reading time');

        expect($attribute->name)->toBe('theme/reading-time');
        expect($attribute->label)->toBe('Reading time');
        expect($attribute->usesContext)->toBe([]);
    });

    it('carries the block context its value needs', function () {
        // WordPress passes nothing a source did not ask for.
        expect(new AsBlockBinding(
            name: 'theme/x',
            label: 'X',
            usesContext: ['postId', 'postType'],
        )->usesContext)->toBe(['postId', 'postType']);
    });

    it('is readonly', function () {
        expect(AsBlockBinding::class)->toBeReadonly();
    });

    it('is a class attribute', function () {
        $attributes = new ReflectionClass(AsBlockBinding::class)->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
        expect($attributes[0]->newInstance()->flags & Attribute::TARGET_CLASS)->toBeTruthy();
    });
});
