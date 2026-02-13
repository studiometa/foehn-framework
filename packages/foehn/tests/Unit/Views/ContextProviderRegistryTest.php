<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\TemplateContext;
use Timber\Site;

// Simple stub for Site to avoid WordPress dependency
class RegistrySiteStub extends Site
{
    public function __construct()
    {
        // Skip parent constructor which requires WordPress
    }
}

function createTestContext(array $extra = []): TemplateContext
{
    return new TemplateContext(
        post: null,
        posts: null,
        site: new RegistrySiteStub(),
        user: null,
        extra: $extra,
    );
}

describe('ContextProviderRegistry', function () {
    it('starts with zero providers', function () {
        $registry = new ContextProviderRegistry();

        expect($registry->count())->toBe(0);
    });

    it('can register a provider for a template', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('foo', 'bar');
            }
        };

        $registry->register(['single'], $provider);

        expect($registry->count())->toBe(1);
        expect($registry->hasProviders('single'))->toBeTrue();
        expect($registry->hasProviders('page'))->toBeFalse();
    });

    it('can register a provider for multiple templates', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context;
            }
        };

        $registry->register(['single', 'page', 'archive'], $provider);

        expect($registry->count())->toBe(3);
        expect($registry->hasProviders('single'))->toBeTrue();
        expect($registry->hasProviders('page'))->toBeTrue();
        expect($registry->hasProviders('archive'))->toBeTrue();
    });

    it('can register wildcard patterns', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context;
            }
        };

        $registry->register(['single-*'], $provider);

        expect($registry->hasProviders('single-post'))->toBeTrue();
        expect($registry->hasProviders('single-product'))->toBeTrue();
        expect($registry->hasProviders('single'))->toBeFalse();
        expect($registry->hasProviders('page'))->toBeFalse();
    });

    it('provides context with matching providers', function () {
        $registry = new ContextProviderRegistry();

        $provider1 = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('provider1', true);
            }
        };

        $provider2 = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('provider2', true);
            }
        };

        $registry->register(['single'], $provider1);
        $registry->register(['single'], $provider2);

        $context = createTestContext(['original' => true]);
        $result = $registry->provide('single', $context);

        expect($result->get('original'))->toBeTrue();
        expect($result->get('provider1'))->toBeTrue();
        expect($result->get('provider2'))->toBeTrue();
    });

    it('respects provider priority', function () {
        $registry = new ContextProviderRegistry();
        $order = [];

        $lowPriority = new class($order) implements ContextProviderInterface {
            public function __construct(
                private array &$order,
            ) {}

            public function provide(TemplateContext $context): TemplateContext
            {
                $this->order[] = 'low';
                return $context;
            }
        };

        $highPriority = new class($order) implements ContextProviderInterface {
            public function __construct(
                private array &$order,
            ) {}

            public function provide(TemplateContext $context): TemplateContext
            {
                $this->order[] = 'high';
                return $context;
            }
        };

        // Register low priority first, but high priority should run first
        $registry->register(['single'], $lowPriority, priority: 20);
        $registry->register(['single'], $highPriority, priority: 5);

        $registry->provide('single', createTestContext());

        expect($order)->toBe(['high', 'low']);
    });

    it('matches wildcard patterns correctly', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('matched', true);
            }
        };

        $registry->register(['single-post-*'], $provider);

        // Should match
        expect($registry->hasProviders('single-post-hello'))->toBeTrue();
        expect($registry->hasProviders('single-post-world'))->toBeTrue();

        // Should not match
        expect($registry->hasProviders('single-post'))->toBeFalse();
        expect($registry->hasProviders('single-product'))->toBeFalse();
    });

    it('combines exact and wildcard matches', function () {
        $registry = new ContextProviderRegistry();

        $exactProvider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('exact', true);
            }
        };

        $wildcardProvider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('wildcard', true);
            }
        };

        $registry->register(['single-post'], $exactProvider);
        $registry->register(['single-*'], $wildcardProvider);

        $result = $registry->provide('single-post', createTestContext());

        expect($result->get('exact'))->toBeTrue();
        expect($result->get('wildcard'))->toBeTrue();
    });

    it('counts wildcard providers correctly', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context;
            }
        };

        // Register only wildcard patterns
        $registry->register(['single-*', 'archive-*'], $provider);

        expect($registry->count())->toBe(2);
    });

    it('returns unmodified context when no providers match', function () {
        $registry = new ContextProviderRegistry();

        $context = createTestContext(['original' => true]);
        $result = $registry->provide('nonexistent', $context);

        expect($result->get('original'))->toBeTrue();
    });
});
