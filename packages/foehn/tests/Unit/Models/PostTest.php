<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsTimberModel;
use Studiometa\Foehn\Concerns\QueriesPostType;
use Studiometa\Foehn\Models\Post;
use Timber\Post as TimberPost;

describe('Foehn\\Models\\Post', function () {
    it('extends Timber\\Post', function () {
        expect(is_subclass_of(Post::class, TimberPost::class))->toBeTrue();
    });

    it('uses QueriesPostType trait', function () {
        $traits = class_uses(Post::class);

        expect($traits)->toContain(QueriesPostType::class);
    });

    it('has AsTimberModel attribute for post type', function () {
        $reflection = new ReflectionClass(Post::class);
        $attributes = $reflection->getAttributes(AsTimberModel::class);

        expect($attributes)->toHaveCount(1);

        $attribute = $attributes[0]->newInstance();

        expect($attribute->name)->toBe('post');
    });

    it('provides static query methods', function () {
        expect(method_exists(Post::class, 'query'))->toBeTrue();
        expect(method_exists(Post::class, 'all'))->toBeTrue();
        expect(method_exists(Post::class, 'find'))->toBeTrue();
        expect(method_exists(Post::class, 'first'))->toBeTrue();
        expect(method_exists(Post::class, 'count'))->toBeTrue();
        expect(method_exists(Post::class, 'exists'))->toBeTrue();
    });
});
