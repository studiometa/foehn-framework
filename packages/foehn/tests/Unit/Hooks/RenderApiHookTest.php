<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\RenderApiConfig;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Hooks\RenderApiHook;
use Studiometa\Foehn\Rest\RenderApi;

beforeEach(function () {
    wp_stub_reset();
});

describe('RenderApiHook', function () {
    it('registers the REST route when called', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(templates: ['partials/*']);
        $renderApi = new RenderApi($view, $config);

        $hook = new RenderApiHook($renderApi);
        $hook->register();

        $routes = wp_stub_get_calls('register_rest_route');
        expect($routes)->toHaveCount(1);
        expect($routes[0]['args']['namespace'])->toBe('foehn/v1');
        expect($routes[0]['args']['route'])->toBe('/render');
    });

    it('has AsAction attribute on register method', function () {
        $reflection = new ReflectionMethod(RenderApiHook::class, 'register');
        $attributes = $reflection->getAttributes(\Studiometa\Foehn\Attributes\AsAction::class);

        expect($attributes)->toHaveCount(1);

        $instance = $attributes[0]->newInstance();
        expect($instance->hook)->toBe('rest_api_init');
    });
});
