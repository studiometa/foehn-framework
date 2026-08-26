<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\TemplateControllerDiscovery;
use Studiometa\Foehn\Views\Sections\SectionCollector;
use Studiometa\Foehn\Views\Sections\SectionRenderer;
use Studiometa\Foehn\Views\Sections\SectionRequest;
use Studiometa\Foehn\Views\Twig\SectionExtension;

#[\Studiometa\Foehn\Attributes\AsTemplateController('index')]
final class SectionTestController implements \Studiometa\Foehn\Contracts\TemplateControllerInterface
{
    public function __construct(
        private readonly SectionExtension $sections,
    ) {}

    public function handle(\Studiometa\Foehn\Views\TemplateContext $context): ?string
    {
        $this->sections->section(['from_page' => true], 'results', ['explicit' => true]);
        $this->sections->section(['from_page' => true], 'filters');

        return '<html><body>Full page</body></html>';
    }
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
        $request = new SectionRequest('GET', '/archive?type=news');

        expect($request->isSelected())->toBeFalse()->and($request->names())->toBe([]);
    });

    it('accepts one or many safe names in requested order', function () {
        expect(new SectionRequest('GET', '/archive?sections=results')->names())->toBe(['results']);
        expect(new SectionRequest('GET', '/archive?sections=filters,results')->names())->toBe([
            'filters',
            'results',
        ]);
    });

    it('accepts GET and HEAD only', function () {
        expect(new SectionRequest('HEAD', '/?sections=results')->isValid())->toBeTrue();

        $request = new SectionRequest('POST', '/?sections=results');

        expect($request->isValid())->toBeFalse()->and($request->errorStatus())->toBe(405);
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
        ['/?sections='],
        ['/?sections=../secret'],
        ['/?sections=Hero'],
        ['/?sections=one,one'],
        ['/?sections=one&sections=two'],
        ['/?sections=one,two,three,four,five,six'],
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
    });

    it('rejects duplicate page declarations because their wrapper IDs would collide', function () {
        $collector = new SectionCollector();
        $collector->declare('results', []);

        expect(fn() => $collector->declare('results', []))->toThrow(LogicException::class);
    });
});

describe('SectionExtension', function () {
    beforeEach(function () {
        $_SERVER['REQUEST_URI'] = '/archive?type=project&sections=old&utm_source=test';
        $this->view = new SectionTestViewEngine();
        $this->collector = new SectionCollector();
    });

    afterEach(function () {
        $_SERVER['REQUEST_URI'] = '/';
    });

    it('registers context-aware safe HTML helpers', function () {
        $extension = new SectionExtension(
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
        $extension = new SectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        $extension->section(['page' => 1, 'title' => 'Archive'], 'results', ['page' => 2]);

        expect($this->view->renders[0]['context'])->toBe(['page' => 2, 'title' => 'Archive']);
        expect($this->collector->context('results'))->toBe(['page' => 2, 'title' => 'Archive']);
    });

    it('collects selected declarations without rendering them', function () {
        $extension = new SectionExtension(
            new SectionRequest('GET', '/archive?sections=results'),
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
        $extension = new SectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        $html = $extension->section([], 'results', lazy: true);

        expect($html)
            ->toContain('data-component="LazyInclude"')
            ->toContain('data-option-src="/archive?type=project&amp;utm_source=test&amp;sections=results"')
            ->toContain('data-ref="loading"')
            ->toContain('data-ref="error" hidden')
            ->not->toContain('foehn-section-results');
        expect($this->view->renders)->toBe([]);
    });

    it('builds a section URL from normal query state and replaces old selection', function () {
        $extension = new SectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect($extension->url('results'))->toBe('/archive?type=project&utm_source=test&sections=results');
    });

    it('rejects names that cannot map safely to a section template', function () {
        $extension = new SectionExtension(
            new SectionRequest('GET', '/archive'),
            $this->collector,
            new SectionRenderer($this->view),
        );

        expect(fn() => $extension->section([], '../secret'))->toThrow(InvalidArgumentException::class);
        expect(fn() => $extension->url('Hero'))->toThrow(InvalidArgumentException::class);
    });

    it('rejects nested declarations that could not be selected from the page shell', function () {
        $renderer = new SectionRenderer($this->view);
        $extension = new SectionExtension(new SectionRequest('GET', '/archive'), $this->collector, $renderer);
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
        $request = new SectionRequest('GET', '/?sections=results');
        $collector = new SectionCollector();
        $view = new SectionTestViewEngine();
        $renderer = new SectionRenderer($view);
        $extension = new SectionExtension($request, $collector, $renderer);
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
        expect(http_response_code())->toBe(200);
        expect($body)->toContain('id="foehn-section-results"')->not->toContain('Full page');
        expect($view->renders)->toHaveCount(1);
        expect($view->renders[0])->toBe([
            'template' => 'sections/results',
            'context' => ['from_page' => true, 'explicit' => true],
        ]);
    });

    it('emits no partial HTML or exception details when one selected render fails', function () {
        wp_stub_reset();
        $GLOBALS['wp_stub_template'] = 'index';
        $container = bootTestContainer();
        $request = new SectionRequest('GET', '/?sections=results,filters');
        $collector = new SectionCollector();
        $view = new SectionTestViewEngine();
        $view->results['sections/results'] = '<p>Partial result</p>';
        $view->results['sections/filters'] = new RuntimeException('Secret exception details');
        $renderer = new SectionRenderer($view);
        $extension = new SectionExtension($request, $collector, $renderer);
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
        $request = new SectionRequest('GET', '/?sections=missing');
        $collector = new SectionCollector();
        $view = new SectionTestViewEngine();
        $renderer = new SectionRenderer($view);
        $extension = new SectionExtension($request, $collector, $renderer);
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
            new SectionRequest('GET', '/?sections=../secret'),
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
            new SectionRequest('GET', '/?sections=results'),
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
            new SectionRequest('HEAD', '/?sections=results'),
            new SectionCollector(),
        );

        ob_start();
        $discovery->handleTemplateInclude('/index.php');
        $body = (string) ob_get_clean();

        expect(http_response_code())->toBe(404)->and($body)->toBe('');
    });
});
