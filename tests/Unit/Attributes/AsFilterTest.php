<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsFilter;

describe('AsFilter', function () {
    it('can be instantiated with hook name only', function () {
        $filter = new AsFilter('the_content');

        expect($filter->hook)->toBe('the_content');
        expect($filter->priority)->toBe(10);
        expect($filter->acceptedArgs)->toBe(1);
    });

    it('can be instantiated with custom priority', function () {
        $filter = new AsFilter('the_content', priority: 20);

        expect($filter->hook)->toBe('the_content');
        expect($filter->priority)->toBe(20);
        expect($filter->acceptedArgs)->toBe(1);
    });

    it('can be instantiated with custom accepted args', function () {
        $filter = new AsFilter('the_posts', acceptedArgs: 2);

        expect($filter->hook)->toBe('the_posts');
        expect($filter->priority)->toBe(10);
        expect($filter->acceptedArgs)->toBe(2);
    });

    it('can be instantiated with all parameters', function () {
        $filter = new AsFilter('the_posts', priority: 5, acceptedArgs: 2);

        expect($filter->hook)->toBe('the_posts');
        expect($filter->priority)->toBe(5);
        expect($filter->acceptedArgs)->toBe(2);
    });

    it('is readonly', function () {
        expect(AsFilter::class)->toBeReadonly();
    });

    it('can be used as an attribute', function () {
        $reflection = new ReflectionClass(AsFilter::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);
    });

    it('can be repeated on methods', function () {
        $reflection = new ReflectionClass(AsFilter::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::IS_REPEATABLE)->toBeTruthy();
    });

    it('targets methods only', function () {
        $reflection = new ReflectionClass(AsFilter::class);
        $attribute = $reflection->getAttributes(Attribute::class)[0]->newInstance();

        expect($attribute->flags & Attribute::TARGET_METHOD)->toBeTruthy();
    });
});
