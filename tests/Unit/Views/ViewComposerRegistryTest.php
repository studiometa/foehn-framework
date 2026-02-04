<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ViewComposerInterface;
use Studiometa\Foehn\Views\ViewComposerRegistry;

describe('ViewComposerRegistry', function () {
    it('starts with zero composers', function () {
        $registry = new ViewComposerRegistry();

        expect($registry->count())->toBe(0);
    });

    it('can register a composer for a template', function () {
        $registry = new ViewComposerRegistry();
        $composer = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return array_merge($context, ['foo' => 'bar']);
            }
        };

        $registry->register(['single'], $composer);

        expect($registry->count())->toBe(1);
        expect($registry->hasComposers('single'))->toBeTrue();
        expect($registry->hasComposers('page'))->toBeFalse();
    });

    it('can register a composer for multiple templates', function () {
        $registry = new ViewComposerRegistry();
        $composer = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return $context;
            }
        };

        $registry->register(['single', 'page', 'archive'], $composer);

        expect($registry->count())->toBe(3);
        expect($registry->hasComposers('single'))->toBeTrue();
        expect($registry->hasComposers('page'))->toBeTrue();
        expect($registry->hasComposers('archive'))->toBeTrue();
    });

    it('can register wildcard patterns', function () {
        $registry = new ViewComposerRegistry();
        $composer = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return $context;
            }
        };

        $registry->register(['single-*'], $composer);

        expect($registry->hasComposers('single-post'))->toBeTrue();
        expect($registry->hasComposers('single-product'))->toBeTrue();
        expect($registry->hasComposers('single'))->toBeFalse();
        expect($registry->hasComposers('page'))->toBeFalse();
    });

    it('composes context with matching composers', function () {
        $registry = new ViewComposerRegistry();

        $composer1 = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return array_merge($context, ['composer1' => true]);
            }
        };

        $composer2 = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return array_merge($context, ['composer2' => true]);
            }
        };

        $registry->register(['single'], $composer1);
        $registry->register(['single'], $composer2);

        $context = $registry->compose('single', ['original' => true]);

        expect($context)->toBe([
            'original' => true,
            'composer1' => true,
            'composer2' => true,
        ]);
    });

    it('respects composer priority', function () {
        $registry = new ViewComposerRegistry();
        $order = [];

        $lowPriority = new class($order) implements ViewComposerInterface {
            public function __construct(
                private array &$order,
            ) {}

            public function compose(array $context): array
            {
                $this->order[] = 'low';
                return $context;
            }
        };

        $highPriority = new class($order) implements ViewComposerInterface {
            public function __construct(
                private array &$order,
            ) {}

            public function compose(array $context): array
            {
                $this->order[] = 'high';
                return $context;
            }
        };

        // Register low priority first, but high priority should run first
        $registry->register(['single'], $lowPriority, priority: 20);
        $registry->register(['single'], $highPriority, priority: 5);

        $registry->compose('single', []);

        expect($order)->toBe(['high', 'low']);
    });

    it('matches wildcard patterns correctly', function () {
        $registry = new ViewComposerRegistry();
        $composer = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return array_merge($context, ['matched' => true]);
            }
        };

        $registry->register(['single-post-*'], $composer);

        // Should match
        expect($registry->hasComposers('single-post-hello'))->toBeTrue();
        expect($registry->hasComposers('single-post-world'))->toBeTrue();

        // Should not match
        expect($registry->hasComposers('single-post'))->toBeFalse();
        expect($registry->hasComposers('single-product'))->toBeFalse();
    });

    it('combines exact and wildcard matches', function () {
        $registry = new ViewComposerRegistry();

        $exactComposer = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return array_merge($context, ['exact' => true]);
            }
        };

        $wildcardComposer = new class implements ViewComposerInterface {
            public function compose(array $context): array
            {
                return array_merge($context, ['wildcard' => true]);
            }
        };

        $registry->register(['single-post'], $exactComposer);
        $registry->register(['single-*'], $wildcardComposer);

        $context = $registry->compose('single-post', []);

        expect($context)->toBe([
            'exact' => true,
            'wildcard' => true,
        ]);
    });
});
