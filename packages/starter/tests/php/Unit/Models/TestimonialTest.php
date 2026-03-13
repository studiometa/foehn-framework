<?php

declare(strict_types=1);

use App\Models\Testimonial;
use Studiometa\Foehn\Attributes\AsPostType;
use Studiometa\Foehn\Models\Post;

describe('Testimonial', function () {
    it('extends Foehn Post model', function () {
        expect(is_subclass_of(Testimonial::class, Post::class))->toBeTrue();
    });

    it('has AsPostType attribute with correct config', function () {
        $ref = new ReflectionClass(Testimonial::class);
        $attrs = $ref->getAttributes(AsPostType::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->name)->toBe('testimonial');
        expect($attr->singular)->toBe('Témoignage');
        expect($attr->plural)->toBe('Témoignages');
        expect($attr->public)->toBeFalse();
        expect($attr->showInRest)->toBeTrue();
        expect($attr->menuIcon)->toBe('dashicons-format-quote');
        expect($attr->supports)->toBe(['title', 'editor', 'thumbnail']);
    });

    it('declares expected accessor methods', function () {
        $ref = new ReflectionClass(Testimonial::class);

        expect($ref->hasMethod('authorName'))->toBeTrue();
        expect($ref->hasMethod('authorRole'))->toBeTrue();
        expect($ref->hasMethod('company'))->toBeTrue();
        expect($ref->hasMethod('rating'))->toBeTrue();
    });
});
