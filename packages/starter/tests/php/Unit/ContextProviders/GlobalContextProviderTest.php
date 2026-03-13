<?php

declare(strict_types=1);

use App\ContextProviders\GlobalContextProvider;
use Studiometa\Foehn\Attributes\AsContextProvider;
use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Site;

describe('GlobalContextProvider', function () {
    it('implements ContextProviderInterface', function () {
        expect(is_subclass_of(GlobalContextProvider::class, ContextProviderInterface::class))->toBeTrue();
    });

    it('has AsContextProvider attribute matching all templates', function () {
        $ref = new ReflectionClass(GlobalContextProvider::class);
        $attrs = $ref->getAttributes(AsContextProvider::class);

        expect($attrs)->toHaveCount(1);
        expect($attrs[0]->newInstance()->templates)->toBe('*');
    });

    it('adds current_year and is_home to context', function () {
        $provider = new GlobalContextProvider();
        $context = new TemplateContext(post: null, posts: null, site: new Site(), user: null);

        $result = $provider->provide($context);

        expect($result['current_year'])->toBe(date('Y'));
        expect($result)->toHaveKey('is_home');
    });
});
