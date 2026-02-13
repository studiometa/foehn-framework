<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\TemplateContext;
use Studiometa\Foehn\Views\TimberViewEngine;

describe('TimberViewEngine', function () {
    beforeEach(function () {
        $this->contextProviders = new ContextProviderRegistry();
        $this->engine = new TimberViewEngine($this->contextProviders);
    });

    it('starts with no shared data', function () {
        expect($this->engine->getShared())->toBe([]);
    });

    it('can share data', function () {
        $this->engine->share('site_name', 'My Site');

        expect($this->engine->getShared())->toBe(['site_name' => 'My Site']);
    });

    it('can share multiple values', function () {
        $this->engine->share('key1', 'value1');
        $this->engine->share('key2', 'value2');
        $this->engine->share('key3', ['nested' => 'data']);

        expect($this->engine->getShared())->toBe([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => ['nested' => 'data'],
        ]);
    });

    it('overwrites shared data with same key', function () {
        $this->engine->share('key', 'original');
        $this->engine->share('key', 'updated');

        expect($this->engine->getShared())->toBe(['key' => 'updated']);
    });

    it('can share null values', function () {
        $this->engine->share('nullable', null);

        expect($this->engine->getShared())->toHaveKey('nullable');
        expect($this->engine->getShared()['nullable'])->toBeNull();
    });

    it('can share objects', function () {
        $object = new stdClass();
        $object->name = 'test';

        $this->engine->share('object', $object);

        expect($this->engine->getShared()['object'])->toBe($object);
    });
});

describe('TimberViewEngine template resolution', function () {
    beforeEach(function () {
        $this->contextProviders = new ContextProviderRegistry();
        $this->engine = new TimberViewEngine($this->contextProviders);
    });

    it('adds twig extension when missing', function () {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->engine);
        $method = $reflection->getMethod('resolveTemplate');

        expect($method->invoke($this->engine, 'components/button'))->toBe('components/button.twig');
        expect($method->invoke($this->engine, 'layouts/main'))->toBe('layouts/main.twig');
    });

    it('keeps twig extension when present', function () {
        $reflection = new ReflectionClass($this->engine);
        $method = $reflection->getMethod('resolveTemplate');

        expect($method->invoke($this->engine, 'components/button.twig'))->toBe('components/button.twig');
        expect($method->invoke($this->engine, 'page.twig'))->toBe('page.twig');
    });

    it('handles nested paths', function () {
        $reflection = new ReflectionClass($this->engine);
        $method = $reflection->getMethod('resolveTemplate');

        expect($method->invoke($this->engine, 'blocks/hero/hero'))->toBe('blocks/hero/hero.twig');
        expect($method->invoke($this->engine, 'partials/header/navigation.twig'))
            ->toBe('partials/header/navigation.twig');
    });
});

describe('TimberViewEngine context providers', function () {
    it('passes TemplateContext to providers', function () {
        $capturedContext = null;
        $capturingProvider = new class($capturedContext) implements ContextProviderInterface {
            public function __construct(
                private mixed &$captured,
            ) {}

            public function provide(TemplateContext $context): TemplateContext
            {
                $this->captured = $context;

                return $context;
            }
        };

        $registry = new ContextProviderRegistry();
        $registry->register(['*'], $capturingProvider, 0);

        $engine = new TimberViewEngine($registry);

        try {
            $engine->render('test-template', ['custom' => 'value']);
        } catch (RuntimeException) {
            // Expected: Timber::compile returns false for non-existent template
        }

        // Verify TemplateContext is passed
        expect($capturedContext)->toBeInstanceOf(TemplateContext::class);
        expect($capturedContext->get('custom'))->toBe('value');
    });

    it('allows providers to modify context', function () {
        $modifyingProvider = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('added_by_provider', 'yes');
            }
        };

        $capturedContext = null;
        $capturingProvider = new class($capturedContext) implements ContextProviderInterface {
            public function __construct(
                private mixed &$captured,
            ) {}

            public function provide(TemplateContext $context): TemplateContext
            {
                $this->captured = $context;

                return $context;
            }
        };

        $registry = new ContextProviderRegistry();
        $registry->register(['*'], $modifyingProvider, 5);
        $registry->register(['*'], $capturingProvider, 10);

        $engine = new TimberViewEngine($registry);

        try {
            $engine->render('test-template', []);
        } catch (RuntimeException) {
            // Expected
        }

        // The second provider should see data added by the first
        expect($capturedContext->get('added_by_provider'))->toBe('yes');
    });
});
