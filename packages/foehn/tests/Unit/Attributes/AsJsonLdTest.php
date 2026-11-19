<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsJsonLd;

describe('AsJsonLd', function () {
    it('gives one post type as a list', function () {
        expect(new AsJsonLd('event')->getPostTypes())->toBe(['event']);
    });

    it('gives several post types as a list', function () {
        expect(new AsJsonLd(['event', 'place'])->getPostTypes())->toBe(['event', 'place']);
    });

    it('is a class attribute', function () {
        $attributes = new ReflectionClass(AsJsonLd::class)->getAttributes(Attribute::class);

        expect($attributes[0]->newInstance()->flags)->toBe(Attribute::TARGET_CLASS);
    });
});
