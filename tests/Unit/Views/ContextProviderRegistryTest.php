<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\ContextProviderRegistry;

describe('ContextProviderRegistry', function () {
    it('starts with zero providers', function () {
        $registry = new ContextProviderRegistry();

        expect($registry->count())->toBe(0);
    });

    it('can register a provider for a template', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(array $context): array
            {
                return array_merge($context, ['foo' => 'bar']);
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
            public function provide(array $context): array
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
            public function provide(array $context): array
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
            public function provide(array $context): array
            {
                return array_merge($context, ['provider1' => true]);
            }
        };

        $provider2 = new class implements ContextProviderInterface {
            public function provide(array $context): array
            {
                return array_merge($context, ['provider2' => true]);
            }
        };

        $registry->register(['single'], $provider1);
        $registry->register(['single'], $provider2);

        $context = $registry->provide('single', ['original' => true]);

        expect($context)->toBe([
            'original' => true,
            'provider1' => true,
            'provider2' => true,
        ]);
    });

    it('respects provider priority', function () {
        $registry = new ContextProviderRegistry();
        $order = [];

        $lowPriority = new class($order) implements ContextProviderInterface {
            public function __construct(
                private array &$order,
            ) {}

            public function provide(array $context): array
            {
                $this->order[] = 'low';
                return $context;
            }
        };

        $highPriority = new class($order) implements ContextProviderInterface {
            public function __construct(
                private array &$order,
            ) {}

            public function provide(array $context): array
            {
                $this->order[] = 'high';
                return $context;
            }
        };

        // Register low priority first, but high priority should run first
        $registry->register(['single'], $lowPriority, priority: 20);
        $registry->register(['single'], $highPriority, priority: 5);

        $registry->provide('single', []);

        expect($order)->toBe(['high', 'low']);
    });

    it('matches wildcard patterns correctly', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(array $context): array
            {
                return array_merge($context, ['matched' => true]);
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
            public function provide(array $context): array
            {
                return array_merge($context, ['exact' => true]);
            }
        };

        $wildcardProvider = new class implements ContextProviderInterface {
            public function provide(array $context): array
            {
                return array_merge($context, ['wildcard' => true]);
            }
        };

        $registry->register(['single-post'], $exactProvider);
        $registry->register(['single-*'], $wildcardProvider);

        $context = $registry->provide('single-post', []);

        expect($context)->toBe([
            'exact' => true,
            'wildcard' => true,
        ]);
    });

    it('counts wildcard providers correctly', function () {
        $registry = new ContextProviderRegistry();
        $provider = new class implements ContextProviderInterface {
            public function provide(array $context): array
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

        $context = $registry->provide('nonexistent', ['original' => true]);

        expect($context)->toBe(['original' => true]);
    });
});
