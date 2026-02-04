<?php

declare(strict_types=1);

use Studiometa\WPTempest\Attributes\AsAction;
use Studiometa\WPTempest\Attributes\AsFilter;
use Studiometa\WPTempest\Discovery\HookDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

beforeEach(function () {
    $this->discovery = new HookDiscovery();
    $this->discovery->setItems(new DiscoveryItems());
    $this->location = new DiscoveryLocation(
        namespace: 'App\\Test',
        path: __DIR__,
    );
});

describe('HookDiscovery caching', function () {
    it('converts action items to cacheable format', function () {
        $attribute = new AsAction('init', 10, 1);

        // Create mock method and class reflectors
        $classReflector = new class {
            public function getName(): string
            {
                return 'App\\Hooks\\MyHooks';
            }
        };

        $methodReflector = new class ($classReflector) {
            public function __construct(private object $class) {}

            public function getDeclaringClass(): object
            {
                return $this->class;
            }

            public function getName(): string
            {
                return 'onInit';
            }
        };

        $this->discovery->getItems()->add($this->location, [
            'type' => 'action',
            'attribute' => $attribute,
            'method' => $methodReflector,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'type' => 'action',
            'hook' => 'init',
            'className' => 'App\\Hooks\\MyHooks',
            'methodName' => 'onInit',
            'priority' => 10,
            'acceptedArgs' => 1,
        ]);
    });

    it('converts filter items to cacheable format', function () {
        $attribute = new AsFilter('the_content', 20, 2);

        $classReflector = new class {
            public function getName(): string
            {
                return 'App\\Hooks\\ContentFilter';
            }
        };

        $methodReflector = new class ($classReflector) {
            public function __construct(private object $class) {}

            public function getDeclaringClass(): object
            {
                return $this->class;
            }

            public function getName(): string
            {
                return 'filterContent';
            }
        };

        $this->discovery->getItems()->add($this->location, [
            'type' => 'filter',
            'attribute' => $attribute,
            'method' => $methodReflector,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0])->toBe([
            'type' => 'filter',
            'hook' => 'the_content',
            'className' => 'App\\Hooks\\ContentFilter',
            'methodName' => 'filterContent',
            'priority' => 20,
            'acceptedArgs' => 2,
        ]);
    });

    it('handles multiple hooks', function () {
        $actionAttribute = new AsAction('init', 5, 0);
        $filterAttribute = new AsFilter('the_title', 15, 3);

        $classReflector = new class {
            public function getName(): string
            {
                return 'App\\Hooks\\MultiHooks';
            }
        };

        $methodReflector1 = new class ($classReflector) {
            public function __construct(private object $class) {}

            public function getDeclaringClass(): object
            {
                return $this->class;
            }

            public function getName(): string
            {
                return 'onInit';
            }
        };

        $methodReflector2 = new class ($classReflector) {
            public function __construct(private object $class) {}

            public function getDeclaringClass(): object
            {
                return $this->class;
            }

            public function getName(): string
            {
                return 'filterTitle';
            }
        };

        $this->discovery->getItems()->add($this->location, [
            'type' => 'action',
            'attribute' => $actionAttribute,
            'method' => $methodReflector1,
        ]);
        $this->discovery->getItems()->add($this->location, [
            'type' => 'filter',
            'attribute' => $filterAttribute,
            'method' => $methodReflector2,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(2);
        expect($cacheableData[0]['type'])->toBe('action');
        expect($cacheableData[1]['type'])->toBe('filter');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'type' => 'action',
                'hook' => 'wp_head',
                'className' => 'App\\Hooks\\HeadHooks',
                'methodName' => 'addMeta',
                'priority' => 1,
                'acceptedArgs' => 0,
            ],
            [
                'type' => 'filter',
                'hook' => 'body_class',
                'className' => 'App\\Hooks\\BodyHooks',
                'methodName' => 'addBodyClass',
                'priority' => 10,
                'acceptedArgs' => 1,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles default priority and accepted args', function () {
        $attribute = new AsAction('save_post');

        $classReflector = new class {
            public function getName(): string
            {
                return 'App\\Hooks\\PostHooks';
            }
        };

        $methodReflector = new class ($classReflector) {
            public function __construct(private object $class) {}

            public function getDeclaringClass(): object
            {
                return $this->class;
            }

            public function getName(): string
            {
                return 'onSavePost';
            }
        };

        $this->discovery->getItems()->add($this->location, [
            'type' => 'action',
            'attribute' => $attribute,
            'method' => $methodReflector,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData[0]['priority'])->toBe(10);
        expect($cacheableData[0]['acceptedArgs'])->toBe(1);
    });
});
