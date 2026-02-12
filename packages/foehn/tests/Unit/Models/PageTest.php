<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Models\Page;
use Studiometa\Foehn\Models\Post;

describe('Foehn\\Models\\Page', function () {
    it('extends Foehn\\Models\\Post', function () {
        expect(is_subclass_of(Page::class, Post::class))->toBeTrue();
    });

    it('has AsTimberModel attribute for page type', function () {
        $reflection = new ReflectionClass(Page::class);
        $attributes = $reflection->getAttributes(AsTimberModel::class);

        expect($attributes)->toHaveCount(1);

        $attribute = $attributes[0]->newInstance();

        expect($attribute->name)->toBe('page');
    });

    it('inherits query methods from Post', function () {
        expect(method_exists(Page::class, 'query'))->toBeTrue();
        expect(method_exists(Page::class, 'all'))->toBeTrue();
        expect(method_exists(Page::class, 'find'))->toBeTrue();
        expect(method_exists(Page::class, 'first'))->toBeTrue();
        expect(method_exists(Page::class, 'count'))->toBeTrue();
        expect(method_exists(Page::class, 'exists'))->toBeTrue();
    });
});
