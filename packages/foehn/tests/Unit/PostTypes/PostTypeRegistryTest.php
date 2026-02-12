<?php

declare(strict_types=1);

use Studiometa\Foehn\PostTypes\PostTypeRegistry;

beforeEach(function () {
    PostTypeRegistry::clear();
});

describe('PostTypeRegistry', function () {
    it('can register a class-to-post-type mapping', function () {
        PostTypeRegistry::register('App\\Models\\Product', 'product');

        expect(PostTypeRegistry::has('App\\Models\\Product'))->toBeTrue();
        expect(PostTypeRegistry::get('App\\Models\\Product'))->toBe('product');
    });

    it('can check if a class is registered', function () {
        expect(PostTypeRegistry::has('App\\Models\\Product'))->toBeFalse();

        PostTypeRegistry::register('App\\Models\\Product', 'product');

        expect(PostTypeRegistry::has('App\\Models\\Product'))->toBeTrue();
    });

    it('throws when getting an unregistered class', function () {
        expect(fn() => PostTypeRegistry::get('App\\Models\\Unknown'))
            ->toThrow(RuntimeException::class, 'is not registered');
    });

    it('can get all registered mappings', function () {
        PostTypeRegistry::register('App\\Models\\Product', 'product');
        PostTypeRegistry::register('App\\Models\\Event', 'event');

        $all = PostTypeRegistry::all();

        expect($all)->toHaveCount(2);
        expect($all['App\\Models\\Product'])->toBe('product');
        expect($all['App\\Models\\Event'])->toBe('event');
    });

    it('can clear the registry', function () {
        PostTypeRegistry::register('App\\Models\\Product', 'product');

        expect(PostTypeRegistry::has('App\\Models\\Product'))->toBeTrue();

        PostTypeRegistry::clear();

        expect(PostTypeRegistry::has('App\\Models\\Product'))->toBeFalse();
        expect(PostTypeRegistry::all())->toBe([]);
    });

    it('overwrites existing mapping for same class', function () {
        PostTypeRegistry::register('App\\Models\\Product', 'product');
        PostTypeRegistry::register('App\\Models\\Product', 'shop_product');

        expect(PostTypeRegistry::get('App\\Models\\Product'))->toBe('shop_product');
    });
});
