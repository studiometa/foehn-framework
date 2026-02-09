<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\RenderApiConfig;
use Studiometa\Foehn\Contracts\TimberRepositoryInterface;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Rest\RenderApi;
use Timber\Menu;
use Timber\Post;
use Timber\PostQuery;
use Timber\Term;
use Timber\Timber;
use Timber\User;

/**
 * Create a mock TimberRepositoryInterface.
 */
function createMockTimber(?Post $post = null, ?Term $term = null): TimberRepositoryInterface
{
    return new class($post, $term) implements TimberRepositoryInterface {
        public function __construct(
            private ?Post $mockPost,
            private ?Term $mockTerm,
        ) {}

        public function post(int $id, bool $publishedOnly = true): ?Post
        {
            return $this->mockPost;
        }

        public function currentPost(): ?Post
        {
            return null;
        }

        public function posts(array $args = []): ?PostQuery
        {
            return null;
        }

        public function term(int $id, string $taxonomy = 'category'): ?Term
        {
            return $this->mockTerm;
        }

        public function termBySlug(string $slug, string $taxonomy = 'category'): ?Term
        {
            return null;
        }

        public function terms(string|array $args = []): iterable
        {
            return [];
        }

        public function menu(string $location): ?Menu
        {
            return null;
        }

        public function menuBySlug(string $slug): ?Menu
        {
            return null;
        }

        public function currentUser(): ?User
        {
            return null;
        }

        public function user(int $id): ?User
        {
            return null;
        }

        public function context(): array
        {
            return [];
        }

        public function globalContext(): array
        {
            return [];
        }
    };
}

beforeEach(function () {
    wp_stub_reset();
});

afterEach(function () {
    wp_stub_reset();
    // Reset Timber context cache
    $reflection = new ReflectionClass(Timber::class);
    $property = $reflection->getProperty('context_cache');
    $property->setValue(null, []);
});

describe('RenderApi', function () {
    it('has correct namespace and route constants', function () {
        expect(RenderApi::NAMESPACE)->toBe('foehn/v1');
        expect(RenderApi::ROUTE)->toBe('/render');
    });

    it('does not register route when disabled', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: false, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());
        $api->register();

        $actions = wp_stub_get_calls('add_action');
        expect($actions)->toBeEmpty();
    });

    it('registers rest_api_init action when enabled', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());
        $api->register();

        $actions = wp_stub_get_calls('add_action');
        expect($actions)->toHaveCount(1);
        expect($actions[0]['args']['hook'])->toBe('rest_api_init');
    });

    it('registers route with correct parameters', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());
        $api->register();

        // Trigger rest_api_init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $routes = wp_stub_get_calls('register_rest_route');
        expect($routes)->toHaveCount(1);
        expect($routes[0]['args']['namespace'])->toBe('foehn/v1');
        expect($routes[0]['args']['route'])->toBe('/render');
        expect($routes[0]['args']['args']['methods'])->toBe('GET');
        expect($routes[0]['args']['args']['permission_callback'])->toBe('__return_true');
    });
});

describe('RenderApi handle', function () {
    it('returns 404 for templates not in allowlist', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'blocks/hero',
                'templates' => null,
                default => null,
            });

        $response = $api->handle($request);

        expect($response->get_status())->toBe(404);
        expect($response->get_data()['code'])->toBe('template_not_allowed');
    });

    it('renders template with scalar context params', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view
            ->method('render')
            ->with('partials/card', ['title' => 'Hello', 'count' => '5'])
            ->willReturn('<div>Hello</div>');

        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                'post_id' => null,
                'term_id' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'template' => 'partials/card',
                'templates' => null,
                'title' => 'Hello',
                'count' => '5',
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_data()['html'])->toBe('<div>Hello</div>');
    });

    it('returns 404 when template render fails', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willThrowException(new RuntimeException('Template not found'));

        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/nonexistent',
                'templates' => null,
                'post_id' => null,
                'term_id' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'template' => 'partials/nonexistent',
                'templates' => null,
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(404);
        expect($response->get_data()['code'])->toBe('render_error');
    });

    it('filters out non-scalar context values', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->with('partials/card', ['title' => 'Hello'])->willReturn('<div>Hello</div>');

        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                'templates' => null,
                'post_id' => null,
                'term_id' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'template' => 'partials/card',
                'templates' => null,
                'title' => 'Hello',
                'items' => ['should', 'be', 'ignored'], // Non-scalar
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
    });

    it('returns 400 when neither template nor templates provided', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnCallback(fn($key) => null);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(400);
        expect($response->get_data()['code'])->toBe('missing_template');
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

        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => null,
                'templates' => ['hero' => 'partials/hero', 'card' => 'partials/card'],
                'post_id' => null,
                'term_id' => null,
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

    it('returns 404 when one template is not allowed', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => null,
                'templates' => ['hero' => 'partials/hero', 'secret' => 'admin/secret'],
                'post_id' => null,
                'term_id' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'templates' => ['hero' => 'partials/hero', 'secret' => 'admin/secret'],
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(404);
        expect($response->get_data()['code'])->toBe('template_not_allowed');
    });

    it('returns 404 when one template fails to render', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $view->method('render')->willReturnCallback(function ($template) {
            if ($template === 'partials/broken') {
                throw new RuntimeException('Render failed');
            }

            return '<div>OK</div>';
        });

        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);

        $api = new RenderApi($view, $config, createMockTimber());

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => null,
                'templates' => ['good' => 'partials/good', 'broken' => 'partials/broken'],
                'post_id' => null,
                'term_id' => null,
                default => null,
            });
        $request
            ->method('get_params')
            ->willReturn([
                'templates' => ['good' => 'partials/good', 'broken' => 'partials/broken'],
            ]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(404);
        expect($response->get_data()['code'])->toBe('render_error');
    });
});

describe('RenderApi context resolution', function () {
    it('resolves post_id to post context', function () {
        $mockPost = $this->createMock(Post::class);

        $view = $this->createMock(ViewEngineInterface::class);
        $view
            ->method('render')
            ->with('partials/card', $this->callback(fn($ctx) => isset($ctx['post']) && $ctx['post'] === $mockPost))
            ->willReturn('<article>Post</article>');

        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);
        $timber = createMockTimber(post: $mockPost);

        $api = new RenderApi($view, $config, $timber);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                'post_id' => 123,
                'term_id' => null,
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'partials/card', 'post_id' => 123]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_data()['html'])->toBe('<article>Post</article>');
    });

    it('returns 404 when post_id not found', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);
        $timber = createMockTimber(); // Returns null for post

        $api = new RenderApi($view, $config, $timber);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/card',
                'templates' => null,
                'post_id' => 999,
                'term_id' => null,
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'partials/card', 'post_id' => 999]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(404);
        expect($response->get_data()['code'])->toBe('invalid_context');
    });

    it('resolves term_id to term context', function () {
        $mockTerm = $this->createMock(Term::class);

        $view = $this->createMock(ViewEngineInterface::class);
        $view
            ->method('render')
            ->with('partials/term', $this->callback(fn($ctx) => isset($ctx['term']) && $ctx['term'] === $mockTerm))
            ->willReturn('<div>Term</div>');

        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);
        $timber = createMockTimber(term: $mockTerm);

        $api = new RenderApi($view, $config, $timber);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/term',
                'templates' => null,
                'post_id' => null,
                'term_id' => 5,
                'taxonomy' => 'category',
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'partials/term', 'term_id' => 5]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(200);
        expect($response->get_data()['html'])->toBe('<div>Term</div>');
    });

    it('returns 404 when term_id not found', function () {
        $view = $this->createMock(ViewEngineInterface::class);
        $config = new RenderApiConfig(enabled: true, templates: ['partials/*']);
        $timber = createMockTimber(); // Returns null for term

        $api = new RenderApi($view, $config, $timber);

        $request = $this->createMock(WP_REST_Request::class);
        $request
            ->method('get_param')
            ->willReturnCallback(fn($key) => match ($key) {
                'template' => 'partials/term',
                'templates' => null,
                'post_id' => null,
                'term_id' => 999,
                'taxonomy' => null,
                default => null,
            });
        $request->method('get_params')->willReturn(['template' => 'partials/term', 'term_id' => 999]);

        $response = $api->handle($request);

        expect($response->get_status())->toBe(404);
        expect($response->get_data()['code'])->toBe('invalid_context');
    });
});
