<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsRestRoute;

describe('AsRestRoute', function () {
    it('can be instantiated with minimal parameters', function () {
        $attribute = new AsRestRoute(namespace: 'theme/v1', route: '/posts');

        expect($attribute->namespace)->toBe('theme/v1');
        expect($attribute->route)->toBe('/posts');
        expect($attribute->method)->toBe('GET');
        expect($attribute->permission)->toBeNull();
        expect($attribute->args)->toBe([]);
    });

    it('can be instantiated with all parameters', function () {
        $attribute = new AsRestRoute(
            namespace: 'theme/v1',
            route: '/posts/(?P<id>\d+)',
            method: 'DELETE',
            permission: 'canDeletePost',
            args: [
                'id' => [
                    'required' => true,
                    'type' => 'integer',
                ],
            ],
        );

        expect($attribute->namespace)->toBe('theme/v1');
        expect($attribute->route)->toBe('/posts/(?P<id>\d+)');
        expect($attribute->method)->toBe('DELETE');
        expect($attribute->permission)->toBe('canDeletePost');
        expect($attribute->args)->toHaveKey('id');
    });

    it('returns correct HTTP method constant', function () {
        expect(new AsRestRoute('ns', '/r', 'GET')->getMethodConstant())->toBe('GET');
        expect(new AsRestRoute('ns', '/r', 'POST')->getMethodConstant())->toBe('POST');
        expect(new AsRestRoute('ns', '/r', 'PUT')->getMethodConstant())->toBe('PUT');
        expect(new AsRestRoute('ns', '/r', 'PATCH')->getMethodConstant())->toBe('PATCH');
        expect(new AsRestRoute('ns', '/r', 'DELETE')->getMethodConstant())->toBe('DELETE');
        expect(new AsRestRoute('ns', '/r', 'get')->getMethodConstant())->toBe('GET');
        expect(new AsRestRoute('ns', '/r', 'invalid')->getMethodConstant())->toBe('GET');
    });

    it('is readonly', function () {
        expect(AsRestRoute::class)->toBeReadonly();
    });

    it('is a repeatable method attribute', function () {
        $reflection = new ReflectionClass(AsRestRoute::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        expect($attributes)->toHaveCount(1);

        $attributeInstance = $attributes[0]->newInstance();
        expect($attributeInstance->flags & Attribute::TARGET_METHOD)->toBeTruthy();
        expect($attributeInstance->flags & Attribute::IS_REPEATABLE)->toBeTruthy();
    });
});
