<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views;

use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Loader;
use Timber\Site;
use Timber\Timber;
use Twig\Error\Error as TwigError;

/**
 * Timber/Twig implementation of ViewEngineInterface.
 *
 * Wraps Timber's template rendering with context provider support
 * and shared data management.
 *
 * A failure names the view that was asked for. A Twig error keeps its message,
 * its template and its line, and a missing template lists the directories
 * Timber searched: see ViewRenderingFailed.
 */
final class TimberViewEngine implements ViewEngineInterface
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(
        private readonly ContextProviderRegistry $contextProviders,
    ) {}

    /**
     * @inheritDoc
     */
    public function render(string $template, array|object $context = []): string
    {
        $resolved = $this->resolveTemplate($template);

        // Convert to TemplateContext if needed
        $templateContext = $this->toTemplateContext($context);

        // Apply context providers
        $templateContext = $this->contextProviders->provide($template, $templateContext);

        // Convert to array and merge with Timber globals and shared data
        $contextArray = array_merge(Timber::context_global(), $this->shared, $templateContext->toArray());

        try {
            $result = Timber::compile($resolved, $contextArray);
        } catch (TwigError $error) {
            throw ViewRenderingFailed::inTwig($template, $error);
        }

        // Timber answers false, and logs, when no directory holds the file.
        if ($result === false) {
            throw $this->notFound([$template => $resolved]);
        }

        return is_string($result) ? $result : '';
    }

    /**
     * @inheritDoc
     */
    public function renderFirst(array $templates, array|object $context = []): string
    {
        foreach ($templates as $template) {
            if (!$this->exists($template)) {
                continue;
            }

            return $this->render($template, $context);
        }

        $files = [];

        foreach ($templates as $template) {
            $files[$template] = $this->resolveTemplate($template);
        }

        throw $this->notFound($files);
    }

    /**
     * Convert context to TemplateContext.
     *
     * @param array<string, mixed>|object $context
     * @return TemplateContext
     */
    private function toTemplateContext(array|object $context): TemplateContext
    {
        if ($context instanceof TemplateContext) {
            return $context;
        }

        $array = match (true) {
            is_array($context) => $context,
            method_exists($context, 'toArray') => $context->toArray(),
            default => get_object_vars($context),
        };

        return new TemplateContext(
            post: $array['post'] ?? null,
            posts: $array['posts'] ?? null,
            site: $array['site'] ?? new Site(),
            user: $array['user'] ?? null,
            extra: array_diff_key($array, array_flip(['post', 'posts', 'site', 'user'])),
        );
    }

    /**
     * @inheritDoc
     *
     * Asked of Timber's own loader, so a template is found here exactly when
     * rendering it would find it.
     */
    public function exists(string $template): bool
    {
        return new Loader()
            ->get_loader()
            ->exists($this->resolveTemplate($template));
    }

    /**
     * The error for views no directory holds, with the directories Timber searched.
     *
     * The fix points at the first of them named after Timber's template directory
     * (`templates`), where the project keeps its views: Timber searches the theme
     * root first, and it is not where a view belongs.
     *
     * @param array<string, string> $files The file of each view, by view name
     */
    private function notFound(array $files): ViewRenderingFailed
    {
        // Timber also adds the filesystem root, so that a view can be named by its
        // absolute path. No relative view name belongs there, so it is left out.
        $directories = array_values(array_filter(
            array_map(static fn(string $path): string => rtrim($path, '/'), new Loader()->get_loader()->getPaths()),
            static fn(string $path): bool => $path !== '',
        ));
        /** @var string|list<string> $dirname */
        $dirname = Timber::$dirname;
        $names = is_string($dirname) ? [$dirname] : $dirname;
        $templates = array_find($directories, static fn(string $directory): bool => in_array(
            basename($directory),
            $names,
            true,
        ));

        return ViewRenderingFailed::notFound($files, $directories, $templates ?? $directories[0] ?? null);
    }

    /**
     * @inheritDoc
     */
    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function getShared(): array
    {
        return $this->shared;
    }

    /**
     * Resolve template name to file path.
     *
     * Adds .twig extension if not present.
     *
     * @param string $template Template name
     * @return string Resolved template path
     */
    private function resolveTemplate(string $template): string
    {
        if (str_ends_with($template, '.twig')) {
            return $template;
        }

        return $template . '.twig';
    }
}
