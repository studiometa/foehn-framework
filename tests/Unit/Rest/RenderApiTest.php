<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\RenderApiConfig;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Rest\RenderApi;

beforeEach(function () {
    wp_stub_reset();
});

describe('RenderApi', function () {
    it('has correct namespace and route constants', function () {
        expect(RenderApi::NAMESPACE)->toBe('foehn/v1');
        expect(RenderApi::ROUTE)->toBe('/render');
    });

    it('registers route with correct parameters', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);
        $api->register();

        $routes = wp_stub_get_calls('register_rest_route');
        expect($routes)->toHaveCount(1);
        expect($routes[0]['args']['namespace'])->toBe('foehn/v1');
        expect($routes[0]['args']['route'])->toBe('/render');
        expect($routes[0]['args']['args']['methods'])->toBe('GET');
        expect($routes[0]['args']['args']['permission_callback'])->toBe('__return_true');
    });
});

describe('RenderApi handle', function () {
    it('returns 403 for templates not in allowlist', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'blocks/hero',
                'templates' => null,
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'blocks/hero']);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(403);
        expect($response->get_data()['code'])->toBe('template_not_allowed');
    });

    it('renders template with scalar context params', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view
            ->method('render')
            ->with('partials/card', ['title' => 'Hello', 'count' => '5'])
            ->willReturn('<div>Hello</div>');

        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'template' => 'partials/card',
                'title' => 'Hello',
                'count' => '5',
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_data()['html'])->toBe('<div>Hello</div>');
    });

    it('sets Cache-Control header when cacheMaxAge is configured', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willReturn('<div>Cached</div>');

        $config = new RenderApiConfig(templates: ['partials/*'], cacheMaxAge: 300);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'partials/card']);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_headers()['Cache-Control'])->toBe('public, max-age=300');
    });

    it('does not set Cache-Control header when cacheMaxAge is 0', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willReturn('<div>No cache</div>');

        $config = new RenderApiConfig(templates: ['partials/*'], cacheMaxAge: 0);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'partials/card']);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_headers())->not->toHaveKey('Cache-Control');
    });

    it('returns 500 when template render fails', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willThrowException(new RuntimeException('Template not found'));

        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/nonexistent',
                'templates' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'template' => 'partials/nonexistent',
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(500);
        expect($response->get_data()['code'])->toBe('render_error');
    });

    it('filters out non-scalar context values', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->with('partials/card', ['title' => 'Hello'])->willReturn('<div>Hello</div>');

        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'template' => 'partials/card',
                'title' => 'Hello',
                'items' => ['should', 'be', 'ignored'], // Non-scalar
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
    });

    it('returns 400 when neither template nor templates provided', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnCallback(fn($key) => null);
        $request->method('get_params')->willReturn([]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(400);
        expect($response->get_data()['code'])->toBe('missing_template');
    });

    it('returns 400 when templates is not an object of strings', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => null,
                'templates' => ['not-keyed-properly'],
                default => null,
            });
        $request->method('get_params')->willReturn([]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(400);
        expect($response->get_data()['code'])->toBe('invalid_templates');
    });

    it('includes exception message in debug mode', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willThrowException(new RuntimeException('Twig syntax error on line 5'));

        $config = new RenderApiConfig(templates: ['partials/*'], debug: true);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/broken',
                'templates' => null,
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'partials/broken']);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(500);
        expect($response->get_data()['message'])->toContain('Twig syntax error on line 5');
    });

    it('passes post_id and term_id as scalar context values', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view
            ->method('render')
            ->with('partials/card', ['post_id' => 123, 'term_id' => 5, 'taxonomy' => 'category'])
            ->willReturn('<div>Content</div>');

        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'template' => 'partials/card',
                'post_id' => 123,
                'term_id' => 5,
                'taxonomy' => 'category',
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_data()['html'])->toBe('<div>Content</div>');
    });
});

describe('RenderApi handle multiple templates', function () {
    it('renders multiple templates', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willReturnCallback(fn($template) => match ($template) {
            'partials/hero' => '<section>Hero</section>',
            'partials/card' => '<article>Card</article>',
            default => '',
        });

        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => null,
                'templates' => ['hero' => 'partials/hero', 'card' => 'partials/card'],
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'templates' => ['hero' => 'partials/hero', 'card' => 'partials/card'],
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_data())->toBe([
            'hero' => '<section>Hero</section>',
            'card' => '<article>Card</article>',
        ]);
    });

    it('returns 403 when one template is not allowed', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => null,
                'templates' => ['hero' => 'partials/hero', 'secret' => 'admin/secret'],
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'templates' => ['hero' => 'partials/hero', 'secret' => 'admin/secret'],
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(403);
        expect($response->get_data()['code'])->toBe('template_not_allowed');
    });

    it('returns 500 when one template fails to render', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willReturnCallback(function ($template) {
            if ($template === 'partials/broken') {
                throw new RuntimeException('Render failed');
            }

            return '<div>OK</div>';
        });

        $config = new RenderApiConfig(templates: ['partials/*']);

        $api = new RenderApi($view, $config);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => null,
                'templates' => ['good' => 'partials/good', 'broken' => 'partials/broken'],
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'templates' => ['good' => 'partials/good', 'broken' => 'partials/broken'],
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(500);
        expect($response->get_data()['code'])->toBe('render_error');
    });
});
