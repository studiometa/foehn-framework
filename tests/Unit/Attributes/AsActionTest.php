<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsAction;

describe('AsAction', function () {
    it('can be instantiated with hook name only', function () {
        $action = new AsAction('init');

        expect($action->hook)->toBe('init');
        expect($action->priority)->toBe(10);
        expect($action->acceptedArgs)->toBe(1);
    });

    it('can be instantiated with custom priority', function () {
        $action = new AsAction('init', priority: 20);

        expect($action->hook)->toBe('init');
        expect($action->priority)->toBe(20);
        expect($action->acceptedArgs)->toBe(1);
    });

    it('can be instantiated with custom accepted args', function () {
        $action = new AsAction('save_post', acceptedArgs: 3);

        expect($action->hook)->toBe('save_post');
        expect($action->priority)->toBe(10);
        expect($action->acceptedArgs)->toBe(3);
    });

    it('can be instantiated with all parameters', function () {
        $action = new AsAction('save_post', priority: 5, acceptedArgs: 3);

        expect($action->hook)->toBe('save_post');
        expect($action->priority)->toBe(5);
        expect($action->acceptedArgs)->toBe(3);
    });

    it('is readonly', function () {
        expect(AsAction::class)->toBeReadonly();
    });

    it('can be used as an attribute', function () {
        $reflection = new ReflectionClass(AsAction::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
    });

    it('can be repeated on methods', function () {
        $reflection = new ReflectionClass(AsAction::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::IS_REPEATABLE)->toBeTruthy();
    });

    it('targets methods only', function () {
        $reflection = new ReflectionClass(AsAction::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::TARGET_METHOD)->toBeTruthy();
    });
});
