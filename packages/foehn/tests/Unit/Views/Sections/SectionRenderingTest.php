<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Studiometa\Foehn\Views\Sections\SectionCollector;
use Studiometa\Foehn\Views\Sections\SectionRenderer;
use Studiometa\Foehn\Views\Sections\SectionRequest;
use Studiometa\Foehn\Views\Twig\SectionExtension;

#[\Studiometa\Foehn\Attributes\AsTemplateController('index')]
final class SectionTestController implements \Studiometa\Foehn\Contracts\TemplateControllerInterface
{
    public ?Closure $onHandle = null;

    public function __construct(
        private readonly SectionExtension $sections,
    ) {}

    public function handle(\Studiometa\Foehn\Views\TemplateContext $context): ?string
    {
        ($this->onHandle ?? static fn() => null)();
        $this->sections->section(['from_page' => true], 'results', ['explicit' => true]);
        $this->sections->section(['from_page' => true], 'filters');

        return '<html><body>Full page</body></html>';
    }
}

/**
 * Build the extension with the same explicit dependencies as the application container.
 */
function sectionExtension(
    SectionRequest $request,
    SectionCollector $collector,
    SectionRenderer $renderer,
    ?PageCacheConfig $pageCacheConfig = null,
): SectionExtension {
    return new SectionExtension($request, $collector, $renderer, $pageCacheConfig ?? new PageCacheConfig());
}

final class SectionTestViewEngine implements ViewEngineInterface
{
    /** @var list<array{template: string, context: array<string, mixed>|object}> */
    public array $renders = [];

    /** @var array<string, string|Throwable> */
    public array $results = [];

    public ?Closure $onRender = null;

    public function render(string $template, array|object $context = []): string
    {
        $this->renders[] = compact('template', 'context');
        ($this->onRender ?? static fn(string $_template) => null)($template);
        $result = $this->results[$template] ?? '<p>' . $template . '</p>';

        if ($result instanceof Throwable) {
            throw $result;
        }

        return $result;
    }

    public function renderFirst(array $templates, array|object $context = []): string
    {
        return $this->render($templates[0], $context);
    }

    public function exists(string $template): bool
    {
        return true;
    }

    public function share(string $key, mixed $value): void {}

    public function getShared(): array
    {
        return [];
    }
}

describe('SectionRequest', function () {
    it('does not select sections when the control parameter is absent', function () {
        $request = new SectionRequest('GET', '/archive?type=news&sections=features');

        expect($request->isSelected())->toBeFalse()->and($request->names())->toBe([]);
    });

    it('accepts one or many safe names in requested order', function () {
        expect(new SectionRequest('GET', '/archive?foehn_sections=results')->names())->toBe(['results']);
        expect(new SectionRequest('GET', '/archive?foehn_sections=filters,results')->names())->toBe([
            'filters',
            'results',
        ]);
    });

    it('accepts GET and HEAD only', function () {
        expect(new SectionRequest('HEAD', '/?foehn_sections=results')->isValid())->toBeTrue();

        $request = new SectionRequest('POST', '/?foehn_sections=results');

        expect($request->isValid())->toBeFalse()->and($request->errorStatus())->toBe(405);
    });

    it('hides its control parameter from page code and restores the exact request state', function () {
        $previousGet = $_GET;
        $previousRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $_GET = ['type' => 'project', SectionRequest::PARAMETER => 'results'];
        $_SERVER['REQUEST_URI'] = '/archive?type=project&foehn_sections=results&foehn_sections=filters#list';
        $request = new SectionRequest('GET', '/archive?foehn_sections=results');

        try {
            $inside = $request->withoutControlParameter(static fn(): array => [
                $_GET,
                $_SERVER['REQUEST_URI'],
                add_query_arg('paged', 2),
            ]);

            expect($inside)->toBe([
                ['type' => 'project'],
                '/archive?type=project#list',
                '/archive?type=project&paged=2',
            ]);
            expect($_GET)->toBe(['type' => 'project', SectionRequest::PARAMETER => 'results']);
            expect($_SERVER['REQUEST_URI'])
                ->toBe('/archive?type=project&foehn_sections=results&foehn_sections=filters#list');
        } finally {
            $_GET = $previousGet;

            if ($previousRequestUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousRequestUri;
            }
        }
    });

    it('restores absent request state when page code fails', function () {
        $previousGet = $_GET;
        $previousRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $_GET = [];
        unset($_SERVER['REQUEST_URI']);
        $request = new SectionRequest('GET', '/?foehn_sections=results');

        try {
            expect(fn() => $request->withoutControlParameter(static function (): never {
                $_GET[SectionRequest::PARAMETER] = 'changed';
                $_SERVER['REQUEST_URI'] = '/changed';

                throw new RuntimeException('failed');
            }))
                ->toThrow(RuntimeException::class, 'failed');
            expect($_GET)->toBe([]);
            expect(array_key_exists('REQUEST_URI', $_SERVER))->toBeFalse();
        } finally {
            $_GET = $previousGet;

            if ($previousRequestUri !== null) {
                $_SERVER['REQUEST_URI'] = $previousRequestUri;
            }
        }
    });

    it('rejects unsafe, empty, duplicate, repeated, and excessive selections', function (string $uri) {
        $request = new SectionRequest('GET', $uri);

        expect($request->isSelected())
            ->toBeTrue()
            ->and($request->isValid())
            ->toBeFalse()
            ->and($request->errorStatus())
            ->toBe(400);
    })->with([
        ['/?foehn_sections='],
        ['/?foehn_sections=../secret'],
        ['/?foehn_sections=Hero'],
        ['/?foehn_sections=one,one'],
        ['/?foehn_sections=one&foehn_sections=two'],
        ['/?foehn_sections=one,two,three,four,five,six'],
    ]);
});

describe('SectionRenderer', function () {
    beforeEach(function () {
        $this->view = new SectionTestViewEngine();
        $this->renderer = new SectionRenderer($this->view);
    });

    it('renders the conventional template inside the stable wrapper', function () {
        $this->view->results['sections/results'] = '<p>Results</p>';

        expect($this->renderer->render('results', ['page' => 2]))
            ->toBe('<div id="foehn-section-results" data-foehn-section="results"><p>Results</p></div>');
        expect($this->view->renders[0])->toBe([
            'template' => 'sections/results',
            'context' => ['page' => 2],
        ]);
    });

    it('renders selected declarations in request order', function () {
        $collector = new SectionCollector();
        $collector->declare('results', ['name' => 'results']);
        $collector->declare('filters', ['name' => 'filters']);

        $html = $this->renderer->renderSelected(['filters', 'results'], $collector);

        expect($html)->toStartWith('<div id="foehn-section-filters"')->toEndWith('</div>');
        expect(array_column($this->view->renders, 'template'))->toBe(['sections/filters', 'sections/results']);
    });

    it('fails the complete selection when one declaration is missing', function () {
        $collector = new SectionCollector();
        $collector->declare('results', []);

        expect(fn() => $this->renderer->renderSelected(['results', 'missing'], $collector))
            ->toThrow(\Studiometa\Foehn\Views\Sections\SectionNotFoundException::class);
        expect($this->view->renders)->toBe([]);
    });

    it('reports duplicate page declarations without replacing their first context', function () {
        $collector = new SectionCollector();

        expect($collector->declare('results', ['page' => 1]))->toBeTrue();
        expect($collector->declare('results', ['page' => 2]))->toBeFalse();
        expect($collector->context('results'))->toBe(['page' => 1]);
    });
});

describe('SectionExtension', function () {
    beforeEach(function () {
        $_SERVER['REQUEST_URI'] = '/archive?type=project&foehn_sections=old&utm_source=test';
        $this->view = new SectionTestViewEngine();
        $this->collector = new SectionCollector();
    });

    afterEach(function () {
        $_SERVER['REQUEST_URI'] = '/';
    });

    it('registers context-aware safe HTML helpers', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );
        $functions = $extension->getFunctions();

        expect(array_map(static fn($function): string => $function->getName(), $functions))->toBe([
            'foehn_section',
            'foehn_section_url',
        ]);
        expect($functions[0]->needsContext())->toBeTrue();
        expect($functions[0]->getSafe(new \Twig\Node\Nodes()))->toBe(['html']);
    });

    it('lets explicit context override active Twig context on eager requests', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        $extension->section(['page' => 1, 'title' => 'Archive'], 'results', ['page' => 2]);

        expect($this->view->renders[0]['context'])->toBe(['page' => 2, 'title' => 'Archive']);
        expect($this->collector->context('results'))->toBe(['page' => 2, 'title' => 'Archive']);
    });

    it('collects selected declarations without rendering them', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive?foehn_sections=results'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect($extension->section(['page' => 1], 'results', ['page' => 2], true))->toBe('');
        expect($extension->section(['page' => 1], 'filters'))->toBe('');
        expect($this->collector->context('results'))->toBe(['page' => 2]);
        expect($this->collector->has('filters'))->toBeFalse();
        expect($this->view->renders)->toBe([]);
    });

    it('emits a neutral LazyInclude placeholder without rendering providers', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        $html = $extension->section([], 'results', lazy: true);

        expect($html)
            ->toContain('data-component="LazyInclude"')
            ->toContain('data-foehn-lazy-section')
            ->toContain('data-option-src="/archive?type=project&amp;utm_source=test&amp;foehn_sections=results"')
            ->toContain('data-ref="error" role="alert" style="display: none"')
            ->toContain('data-ref="loading" role="status"')
            ->not->toContain(' hidden')
            ->not->toContain('foehn-section-results');
        expect(strpos($html, 'data-ref="error"'))->toBeLessThan(strpos($html, 'data-ref="loading"'));
        expect($this->view->renders)->toBe([]);
    });

    it('builds a section URL from normal query state and replaces old selection', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect($extension->url('results'))->toBe('/archive?type=project&utm_source=test&foehn_sections=results');
    });

    it('builds same-origin section URLs for pagination targets', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect($extension->url('results', 'https://example.com/archive/page/2/?type=project#list'))
            ->toBe('/archive/page/2/?type=project&foehn_sections=results#list');
        expect($extension->url('results', '//evil.example/archive/page/3/?foehn_sections=old'))
            ->toBe('/archive/page/3/?foehn_sections=results')
            ->not->toStartWith('//');
    });

    it('does not freeze ignored query arguments into URLs emitted by cached pages', function () {
        $pageCacheConfig = new PageCacheConfig(
            enabled: true,
            environments: [PageCacheConfig::environment()],
            ignoredQueryArgs: ['utm_source'],
        );
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
            $pageCacheConfig,
        );

        expect($extension->url('results'))->toBe('/archive?type=project&foehn_sections=results');
    });

    it('keeps generated URLs on the current origin for protocol-relative request targets', function () {
        $_SERVER['REQUEST_URI'] = '//evil.example/projects/?type=web';
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect($extension->url('results'))
            ->toBe('/evil.example/projects/?type=web&foehn_sections=results')
            ->not->toStartWith('//');
    });

    it('skips duplicate declarations on normal pages instead of failing the page', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect($extension->section([], 'results'))->toContain('foehn-section-results');
        expect($extension->section([], 'results'))->toBe('');
        expect($this->view->renders)->toHaveCount(1);
    });

    it('rejects duplicate declarations during a selected request', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive?foehn_sections=results'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect($extension->section([], 'results'))->toBe('');
        expect(fn() => $extension->section([], 'results'))
            ->toThrow(LogicException::class, "Section 'results' is declared more than once on this page.");
    });

    it('rejects names that cannot map safely to a section template', function () {
        $extension = sectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect(fn() => $extension->section([], '../secret'))->toThrow(InvalidArgumentException::class);
        expect(fn() => $extension->url('Hero'))->toThrow(InvalidArgumentException::class);
    });

    it('rejects nested declarations that could not be selected from the page shell', function () {
        $renderer = new SectionRenderer($this->view);
        $extension = sectionExtension(new SectionRequest('GET', '/archive'), $this->collector, $renderer);
        $this->view->onRender = static function (string $template) use ($extension): void {
            if ($template === 'sections/results') {
                $extension->section([], 'nested');
            }
        };

        expect(fn() => $extension->section([], 'results'))
            ->toThrow(LogicException::class, 'Sections cannot be nested.');
    });
});

describe('TemplateControllerDiscovery section responses', function () {
    it('runs the matching page controller and emits selected declarations atomically', function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_template'] = 'index';
        $container = bootTestContainer();
        $request = new SectionRequest('GET', '/?foehn_sections=results');
        $collector = new SectionCollector();
        $view = new SectionTestViewEngine();
        $renderer = new SectionRenderer($view);
        $extension = sectionExtension($request, $collector, $renderer);
        $controller = new SectionTestController($extension);
        $observedRequestUris = [];
        $controller->onHandle = static function () use (&$observedRequestUris): void {
            $observedRequestUris[] = $_SERVER['REQUEST_URI'] ?? '';
        };
        $view->onRender = static function () use (&$observedRequestUris): void {
            $observedRequestUris[] = $_SERVER['REQUEST_URI'] ?? '';
        };
        $_GET = ['type' => 'project', SectionRequest::PARAMETER => 'results'];
        $_SERVER['REQUEST_URI'] = '/?type=project&foehn_sections=results';
        $container->singleton(SectionRenderer::class, static fn() => $renderer);
        $container->singleton(SectionTestController::class, static fn() => $controller);
        $discovery = new TemplateControllerDiscovery($request, $collector);
        $discovery->discover(
            testDiscoveryLocation(),
            new \Tempest\Reflection\ClassReflector(SectionTestController::class),
        );
        $discovery->apply();

        try {
            ob_start();
            $template = $discovery->handleTemplateInclude('/index.php');
            $body = (string) ob_get_clean();
        } finally {
            tearDownTestContainer();
        }

        expect($template)->toBe('');
        expect(http_response_code())->toBe(200);
        expect($GLOBALS['wp_stub_status_headers'])->toContain(['code' => 200, 'description' => '']);
        expect($body)->toContain('id="foehn-section-results"')->not->toContain('Full page');
        expect($view->renders)->toHaveCount(1);
        expect($view->renders[0])->toBe([
            'template' => 'sections/results',
            'context' => ['from_page' => true, 'explicit' => true],
        ]);
        expect($observedRequestUris)->toBe(['/?type=project', '/?type=project']);
        expect($_GET)->toBe(['type' => 'project', SectionRequest::PARAMETER => 'results']);
        expect($_SERVER['REQUEST_URI'])->toBe('/?type=project&foehn_sections=results');
    });

    it('emits no partial HTML or exception details when one selected render fails', function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_template'] = 'index';
        $container = bootTestContainer();
        $request = new SectionRequest('GET', '/?foehn_sections=results,filters');
        $collector = new SectionCollector();
        $view = new SectionTestViewEngine();
        $view->results['sections/results'] = '<p>Partial result</p>';
        $view->results['sections/filters'] = new RuntimeException('Secret exception details');
        $renderer = new SectionRenderer($view);
        $extension = sectionExtension($request, $collector, $renderer);
        $container->singleton(SectionRenderer::class, static fn() => $renderer);
        $container->singleton(SectionTestController::class, static fn() => new SectionTestController($extension));
        $discovery = new TemplateControllerDiscovery($request, $collector);
        $discovery->discover(
            testDiscoveryLocation(),
            new \Tempest\Reflection\ClassReflector(SectionTestController::class),
        );
        $discovery->apply();

        try {
            ob_start();
            $template = $discovery->handleTemplateInclude('/index.php');
            $body = (string) ob_get_clean();
        } finally {
            tearDownTestContainer();
        }

        expect($template)->toBe('');
        expect(http_response_code())->toBe(500);
        expect($body)
            ->toContain('<h1>Unable to render sections</h1>')
            ->not->toContain('Partial result')
            ->not->toContain('Secret exception details');
    });

    it('returns 404 when a matching page did not declare the requested section', function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_template'] = 'index';
        $container = bootTestContainer();
        $request = new SectionRequest('GET', '/?foehn_sections=missing');
        $collector = new SectionCollector();
        $view = new SectionTestViewEngine();
        $renderer = new SectionRenderer($view);
        $extension = sectionExtension($request, $collector, $renderer);
        $container->singleton(SectionRenderer::class, static fn() => $renderer);
        $container->singleton(SectionTestController::class, static fn() => new SectionTestController($extension));
        $discovery = new TemplateControllerDiscovery($request, $collector);
        $discovery->discover(
            testDiscoveryLocation(),
            new \Tempest\Reflection\ClassReflector(SectionTestController::class),
        );
        $discovery->apply();

        try {
            ob_start();
            $template = $discovery->handleTemplateInclude('/index.php');
            $body = (string) ob_get_clean();
        } finally {
            tearDownTestContainer();
        }

        expect($template)->toBe('');
        expect(http_response_code())->toBe(404);
        expect($body)->toContain('<h1>Section not found</h1>');
        expect($view->renders)->toBe([]);
    });

    it('returns an HTML error before controller lookup for an invalid request', function () {
        $discovery = new TemplateControllerDiscovery(
            new SectionRequest('GET', '/?foehn_sections=../secret'),
            new SectionCollector(),
        );

        ob_start();
        $template = $discovery->handleTemplateInclude('/index.php');
        $body = (string) ob_get_clean();

        expect($template)->toBe('');
        expect(http_response_code())->toBe(400);
        expect($body)->toContain('<h1>Invalid section request</h1>')->not->toContain('../secret');
    });

    it('returns 404 when no normal page controller matches', function () {
        $discovery = new TemplateControllerDiscovery(
            new SectionRequest('GET', '/?foehn_sections=results'),
            new SectionCollector(),
        );

        ob_start();
        $template = $discovery->handleTemplateInclude('/index.php');
        $body = (string) ob_get_clean();

        expect($template)->toBe('');
        expect(http_response_code())->toBe(404);
        expect($body)->toContain('<h1>Section not found</h1>');
    });

    it('never emits a body for HEAD errors', function () {
        $discovery = new TemplateControllerDiscovery(
            new SectionRequest('HEAD', '/?foehn_sections=results'),
            new SectionCollector(),
        );

        ob_start();
        $discovery->handleTemplateInclude('/index.php');
        $body = (string) ob_get_clean();

        expect(http_response_code())->toBe(404)->and($body)->toBe('');
    });
});
