<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ContextProviderInterface;
use Studiometa\Foehn\Trace\RequestTrace;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\TemplateContext;
use Studiometa\Foehn\Views\TimberViewEngine;
use Studiometa\Foehn\Views\ViewRenderingFailed;
use Timber\Timber;

describe('TimberViewEngine', function () {
    beforeEach(function () {
        $this->contextProviders = new ContextProviderRegistry();
        $this->engine = new TimberViewEngine($this->contextProviders, new RequestTrace());
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
        $this->engine = new TimberViewEngine($this->contextProviders, new RequestTrace());
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

        $engine = new TimberViewEngine($registry, new RequestTrace());

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

        $engine = new TimberViewEngine($registry, new RequestTrace());

        try {
            $engine->render('test-template', []);
        } catch (RuntimeException) {
            // Expected
        }

        // The second provider should see data added by the first
        expect($capturedContext->get('added_by_provider'))->toBe('yes');
    });
});

describe('TimberViewEngine errors', function () {
    beforeEach(function () {
        $this->theme = realpath(testFixturePath('ViewTheme'));
        $this->dirname = Timber::$dirname;
        $GLOBALS['wp_stub_template_directory'] = $this->theme;
        $GLOBALS['wp_stub_stylesheet_directory'] = $this->theme;
        Timber::$dirname = 'templates';

        $this->engine = new TimberViewEngine(new ContextProviderRegistry(), new RequestTrace());
    });

    afterEach(function () {
        unset($GLOBALS['wp_stub_template_directory'], $GLOBALS['wp_stub_stylesheet_directory']);
        Timber::$dirname = $this->dirname;
    });

    it('renders a view it finds', function () {
        expect($this->engine->render('hello', ['title' => 'Hi']))->toContain('<h1>Hi</h1>');
        expect($this->engine->exists('hello'))->toBeTrue();
        expect($this->engine->exists('nowhere'))->toBeFalse();
    });

    it('names the view, the directories it searched and the file to create', function () {
        expect(fn() => $this->engine->render('pages/single'))
            ->toThrow(
                ViewRenderingFailed::class,
                "No template found for the view 'pages/single'. Searched pages/single.twig in: {$this->theme}, {$this->theme}/templates."
                . " Fix: create {$this->theme}/templates/pages/single.twig.",
            );
    });

    it('names every view renderFirst tried', function () {
        expect(fn() => $this->engine->renderFirst(['single-product', 'single']))
            ->toThrow(
                ViewRenderingFailed::class,
                "No template found for the views 'single-product', 'single'. Searched single-product.twig, single.twig in: {$this->theme}, {$this->theme}/templates."
                . " Fix: create one of single-product.twig, single.twig in {$this->theme}/templates.",
            );
    });

    it('tells a traced request the view renderFirst chose and the views it was asked to try', function () {
        $trace = new RequestTrace();
        $trace->begin('a1b2c3');
        $engine = new TimberViewEngine(new ContextProviderRegistry(), $trace);

        $engine->renderFirst(['missing', 'hello'], ['title' => 'Hi']);

        expect($trace->toArray()['candidates'])->toBe([['view' => 'hello', 'candidates' => ['missing', 'hello']]]);
    });

    it('tells a traced request the context providers that ran, in the order they ran', function () {
        $late = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context;
            }
        };
        $early = new class implements ContextProviderInterface {
            public function provide(TemplateContext $context): TemplateContext
            {
                return $context->with('title', 'Hi');
            }
        };
        $registry = new ContextProviderRegistry();
        $registry->register(['hel*'], $late, 20);
        $registry->register(['hello'], $early, 5);
        $trace = new RequestTrace();
        $trace->begin('a1b2c3');

        new TimberViewEngine($registry, $trace)->render('hello');

        expect($trace->toArray()['providers'])->toBe([
            ['view' => 'hello', 'class' => $early::class],
            ['view' => 'hello', 'class' => $late::class],
        ]);
    });

    it('keeps the Twig error, its template and its line, and the view that was asked for', function () {
        try {
            $this->engine->render('page');
        } catch (ViewRenderingFailed $exception) {
            expect($exception->getMessage())
                ->toStartWith("Rendering the view 'page' failed in partials/card.twig at line 2: ");
            expect($exception->getMessage())->toContain('Unable to find template "partials/missing.twig"');
            expect($exception->getPrevious())->toBeInstanceOf(Twig\Error\LoaderError::class);

            return;
        }

        $this->fail('Rendering a template that includes a missing one did not throw.');
    });
});
