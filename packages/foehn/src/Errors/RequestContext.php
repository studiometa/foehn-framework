<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Errors;

use Tempest\Container\Singleton;

/**
 * What the current request renders, for the error entries it writes.
 *
 * Template controller discovery tells it the WordPress template and the controller
 * it matched. The error recorder tells it the last Twig view Timber rendered and the
 * keys of its context. An error entry then says which page, controller and view
 * were running when the error happened.
 *
 * One instance per container: the discovery and the recorder share it.
 */
#[Singleton]
final class RequestContext
{
    /** The context keys an entry keeps. A Timber context can have many more. */
    private const int MAX_KEYS = 40;

    private ?string $template = null;

    private ?string $controller = null;

    private ?string $view = null;

    /** @var list<string> */
    private array $contextKeys = [];

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
    }

    /**
     * The fields an error entry carries about the request.
     *
     * @return array{template: string|null, controller: string|null, view: string|null, context: list<string>}
     */
    public function toArray(): array
    {
        return [
            'template' => $this->template,
            'controller' => $this->controller,
            'view' => $this->view,
            'context' => $this->contextKeys,
        ];
    }
}
