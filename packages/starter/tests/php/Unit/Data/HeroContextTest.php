<?php

declare(strict_types=1);

use App\Data\HeroContext;
use Studiometa\Foehn\Contracts\Arrayable;

describe('HeroContext', function () {
    it('implements Arrayable', function () {
        expect(is_subclass_of(HeroContext::class, Arrayable::class))->toBeTrue();
    });

    it('can be created with required fields only', function () {
        $ctx = new HeroContext(title: 'Hello');

        expect($ctx->title)->toBe('Hello');
        expect($ctx->subtitle)->toBeNull();
        expect($ctx->background)->toBeNull();
        expect($ctx->cta)->toBeNull();
        expect($ctx->height)->toBe('medium');
    });

    it('converts to array with snake_case keys', function () {
        $ctx = new HeroContext(title: 'Test', subtitle: 'Sub', height: 'full');

        $array = $ctx->toArray();

        expect($array)->toHaveKey('title', 'Test');
        expect($array)->toHaveKey('subtitle', 'Sub');
        expect($array)->toHaveKey('height', 'full');
        expect($array)->toHaveKey('background');
        expect($array)->toHaveKey('cta');
    });
});
