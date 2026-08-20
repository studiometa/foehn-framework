<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsPostMeta;

describe('AsPostMeta', function () {
    it('needs only a key', function () {
        $attribute = new AsPostMeta(key: 'price');

        expect($attribute->key)->toBe('price');
        expect($attribute->type)->toBe('string');
        expect($attribute->single)->toBeTrue();
        expect($attribute->objectType)->toBe('post');
        expect($attribute->objectSubtype)->toBeNull();
        expect($attribute->capability)->toBe('edit_posts');
        expect($attribute->sanitize)->toBeNull();
        expect($attribute->schema)->toBe([]);
    });

    it('shows a key in REST by default', function () {
        // Without REST the field is invisible to the editor and to block
        // bindings, which is the whole reason to declare it.
        expect(new AsPostMeta(key: 'price')->showInRest)->toBeTrue();
    });

    it('can be instantiated with every parameter', function () {
        $attribute = new AsPostMeta(
            key: 'gallery',
            type: 'integer',
            single: false,
            showInRest: false,
            description: 'Attachment ids',
            default: 0,
            objectType: 'term',
            objectSubtype: 'genre',
            capability: 'manage_options',
            sanitize: 'sanitizeGallery',
            schema: ['items' => ['type' => 'integer']],
        );

        expect($attribute->type)->toBe('integer');
        expect($attribute->single)->toBeFalse();
        expect($attribute->showInRest)->toBeFalse();
        expect($attribute->description)->toBe('Attachment ids');
        expect($attribute->default)->toBe(0);
        expect($attribute->objectType)->toBe('term');
        expect($attribute->objectSubtype)->toBe('genre');
        expect($attribute->capability)->toBe('manage_options');
        expect($attribute->sanitize)->toBe('sanitizeGallery');
        expect($attribute->schema)->toBe(['items' => ['type' => 'integer']]);
    });

    it('is readonly', function () {
        expect(AsPostMeta::class)->toBeReadonlyClass();
    });

    it('is a repeatable class attribute', function () {
        // A model owns more than one field, and repeating the attribute is what
        // keeps the declarations next to the accessors that read them.
        $attributes = new ReflectionClass(AsPostMeta::class)->getAttributes(Attribute::class);
        $flags = $attributes[0]->newInstance()->flags;

        expect($flags & Attribute::TARGET_CLASS)->toBeTruthy();
        expect($flags & Attribute::IS_REPEATABLE)->toBeTruthy();
    });
});
