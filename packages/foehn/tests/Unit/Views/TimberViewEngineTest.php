<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\TimberViewEngine;
use Timber\Timber;

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

describe('TimberViewEngine context merging', function () {
    beforeEach(function () {
        // Pre-populate Timber's context cache to avoid WordPress dependencies
        $reflection = new ReflectionClass(Timber::class);
        $property = $reflection->getProperty('context_cache');
        $property->setValue(null, [
            'site' => (object) ['name' => 'Test Site', 'url' => 'http://example.com'],
            'theme' => (object) ['name' => 'Test Theme'],
            'user' => false,
            'http_host' => 'http://example.com',
            'wp_title' => 'Test Page',
            'body_class' => 'home page',
        ]);
    });

    afterEach(function () {
        // Reset Timber's context cache
        $reflection = new ReflectionClass(Timber::class);
        $property = $reflection->getProperty('context_cache');
        $property->setValue(null, []);
    });

    it('includes Timber global context in render', function () {
        // Capture the context passed to context providers
        $capturedContext = null;
        $capturingProvider = new class($capturedContext) implements ContextProviderInterface {
            public function __construct(
                private mixed &$captured,
            ) {}

            public function provide(array $context): array
            {
                $this->captured = $context;

                // Return empty array to make Timber::compile return false
                // This triggers the RuntimeException, which we catch
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

        // Verify Timber's global context keys are present
        expect($capturedContext)->toHaveKey('site');
        expect($capturedContext)->toHaveKey('theme');
        expect($capturedContext)->toHaveKey('user');
        expect($capturedContext)->toHaveKey('http_host');
        expect($capturedContext)->toHaveKey('wp_title');
        expect($capturedContext)->toHaveKey('body_class');

        // Verify custom context is also included
        expect($capturedContext)->toHaveKey('custom');
        expect($capturedContext['custom'])->toBe('value');
    });

    it('allows shared data to override Timber global context', function () {
        $capturedContext = null;
        $capturingProvider = new class($capturedContext) implements ContextProviderInterface {
            public function __construct(
                private mixed &$captured,
            ) {}

            public function provide(array $context): array
            {
                $this->captured = $context;

                return $context;
            }
        };

        $registry = new ContextProviderRegistry();
        $registry->register(['*'], $capturingProvider, 0);

        $engine = new TimberViewEngine($registry);
        $engine->share('http_host', 'https://custom-host.com');

        try {
            $engine->render('test-template', []);
        } catch (RuntimeException) {
            // Expected
        }

        // Shared data should override Timber's default
        expect($capturedContext['http_host'])->toBe('https://custom-host.com');
    });

    it('allows render context to override shared data and Timber global context', function () {
        $capturedContext = null;
        $capturingProvider = new class($capturedContext) implements ContextProviderInterface {
            public function __construct(
                private mixed &$captured,
            ) {}

            public function provide(array $context): array
            {
                $this->captured = $context;

                return $context;
            }
        };

        $registry = new ContextProviderRegistry();
        $registry->register(['*'], $capturingProvider, 0);

        $engine = new TimberViewEngine($registry);
        $engine->share('http_host', 'https://shared-host.com');

        try {
            $engine->render('test-template', ['http_host' => 'https://render-host.com']);
        } catch (RuntimeException) {
            // Expected
        }

        // Render context should win over shared data
        expect($capturedContext['http_host'])->toBe('https://render-host.com');
    });
});
