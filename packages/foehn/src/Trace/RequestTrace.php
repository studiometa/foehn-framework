<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Trace;

use Tempest\Container\Singleton;

/**
 * What the current request ran: the WordPress templates, the controller, the views,
 * the context providers and the blocks.
 *
 * Two readers share it:
 *
 * - the error recorder, which writes in each error entry the template, the controller
 *   and the last view with its context keys;
 * - the request tracer, which gives the request an id with begin() and stores
 *   everything at the end of the request, for `wp foehn trace`.
 *
 * The template, the controller and the last view are always kept: an error entry
 * needs them. The rest is kept only once the request has an id, so a request that is
 * not traced keeps no list. Each list has a cap.
 *
 * One instance per container: the discovery, the view engine, the recorder and the
 * tracer share it.
 */
#[Singleton]
final class RequestTrace
{
    /** The context keys kept for each view. A Timber context can have many more. */
    public const int MAX_KEYS = 40;

    /** The items kept in each list: templates tried, views, providers, block names. */
    public const int MAX_ITEMS = 30;

    private ?string $id = null;

    private bool $listening = false;

    private ?string $template = null;

    private ?string $controller = null;

    private ?string $view = null;

    /** @var list<string> */
    private array $contextKeys = [];

    /** @var list<string> */
    private array $hierarchy = [];

    private ?string $templateFile = null;

    /** @var array<string, array{count: int, keys: list<string>}> */
    private array $views = [];

    /** @var list<array{view: string, candidates: list<string>}> */
    private array $candidates = [];

    /** @var list<array{view: string, class: string}> */
    private array $providers = [];

    /** @var array<string, int> */
    private array $blocks = [];

    /**
     * Give the request its id, and start keeping every list.
     */
    public function begin(string $id): void
    {
        $this->id = $id;
    }

    /**
     * The id of the request, or null when it is not traced.
     */
    public function id(): ?string
    {
        return $this->id;
    }

    public function isTracing(): bool
    {
        return $this->id !== null;
    }

    /**
     * Listen to the views Timber renders. Calling it again does nothing.
     */
    public function listen(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        add_filter('timber/loader/render_data', $this->onRenderData(...), PHP_INT_MAX, 2);
    }

    /**
     * `timber/loader/render_data`: remember the view Timber renders and its context keys.
     *
     * The data goes back unchanged.
     */
    public function onRenderData(mixed $data, mixed $file = ''): mixed
    {
        if (is_array($data) && is_string($file)) {
            $this->renderView($file, array_map(strval(...), array_keys($data)));
        }

        return $data;
    }

    /**
     * The WordPress template the request matched, and the controller that handles it.
     *
     * @param string $template The template name, such as `single-product`
     * @param string|null $controller The controller class, or null when no controller handles it
     */
    public function matchTemplate(string $template, ?string $controller): void
    {
        $this->template = $template;
        $this->controller = $controller;
    }

    /**
     * A Twig view Timber renders, with the keys of its context.
     *
     * @param list<string> $contextKeys
     */
    public function renderView(string $view, array $contextKeys): void
    {
        $this->view = $view;
        $this->contextKeys = array_slice($contextKeys, 0, self::MAX_KEYS);

        if (!$this->isTracing()) {
            return;
        }

        if (isset($this->views[$view])) {
            $this->views[$view]['count']++;

            return;
        }

        if (count($this->views) < self::MAX_ITEMS) {
            $this->views[$view] = ['count' => 1, 'keys' => $this->contextKeys];
        }
    }

    /**
     * The template files WordPress looks for, in the order it tries them.
     *
     * @param list<string> $templates
     */
    public function tryTemplates(array $templates): void
    {
        if (!$this->isTracing()) {
            return;
        }

        foreach ($templates as $template) {
            if (!(count($this->hierarchy) < self::MAX_ITEMS && !in_array($template, $this->hierarchy, true))) {
                continue;
            }

            $this->hierarchy[] = $template;
        }
    }

    /**
     * The template file WordPress found, before a controller replaced it.
     */
    public function useTemplateFile(string $file): void
    {
        if ($this->isTracing()) {
            $this->templateFile = $file === '' ? null : $file;
        }
    }

    /**
     * The view `renderFirst()` rendered, and the views it was asked to try.
     *
     * @param list<string> $candidates
     */
    public function renderFirst(array $candidates, string $view): void
    {
        if ($this->isTracing() && count($this->candidates) < self::MAX_ITEMS) {
            $this->candidates[] = ['view' => $view, 'candidates' => array_slice($candidates, 0, self::MAX_ITEMS)];
        }
    }

    /**
     * The context providers that ran for a view, in the order they ran.
     *
     * @param list<string> $classes
     */
    public function provideContext(string $view, array $classes): void
    {
        if (!$this->isTracing()) {
            return;
        }

        foreach ($classes as $class) {
            $provider = ['view' => $view, 'class' => $class];

            if (count($this->providers) < self::MAX_ITEMS && !in_array($provider, $this->providers, true)) {
                $this->providers[] = $provider;
            }
        }
    }

    /**
     * A block WordPress rendered.
     */
    public function renderBlock(string $name): void
    {
        if (!$this->isTracing()) {
            return;
        }

        if (isset($this->blocks[$name])) {
            $this->blocks[$name]++;
        } elseif (count($this->blocks) < self::MAX_ITEMS) {
            $this->blocks[$name] = 1;
        }
    }

    /**
     * The fields an error entry carries about the request.
     *
     * @return array{template: string|null, controller: string|null, view: string|null, context: list<string>, requestId: string|null}
     */
    public function errorFields(): array
    {
        return [
            'template' => $this->template,
            'controller' => $this->controller,
            'view' => $this->view,
            'context' => $this->contextKeys,
            'requestId' => $this->id,
        ];
    }

    /**
     * What the request ran, for the stored trace.
     *
     * @return array{
     *     hierarchy: list<string>,
     *     templateFile: string|null,
     *     template: string|null,
     *     controller: string|null,
     *     views: list<array{view: string, count: int, keys: list<string>}>,
     *     candidates: list<array{view: string, candidates: list<string>}>,
     *     providers: list<array{view: string, class: string}>,
     *     blocks: array<string, int>,
     * }
     */
    public function toArray(): array
    {
        $views = [];

        foreach ($this->views as $view => $rendered) {
            $views[] = ['view' => $view, ...$rendered];
        }

        return [
            'hierarchy' => $this->hierarchy,
            'templateFile' => $this->templateFile,
            'template' => $this->template,
            'controller' => $this->controller,
            'views' => $views,
            'candidates' => $this->candidates,
            'providers' => $this->providers,
            'blocks' => $this->blocks,
        ];
    }
}
