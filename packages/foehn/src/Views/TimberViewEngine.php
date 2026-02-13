<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views;

use RuntimeException;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Timber\Site;
use Timber\Timber;

/**
 * Timber/Twig implementation of ViewEngineInterface.
 *
 * Wraps Timber's template rendering with context provider support
 * and shared data management.
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

        $result = Timber::compile($resolved, $contextArray);

        if ($result === false) {
            throw new RuntimeException("Failed to render template: {$template}");
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

        throw new RuntimeException('No template found: ' . implode(', ', $templates));
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
     */
    public function exists(string $template): bool
    {
        $resolved = $this->resolveTemplate($template);
        /** @var string|list<string> $dirname */
        $dirname = Timber::$dirname;
        $locations = is_string($dirname) ? [$dirname] : $dirname;

        foreach ($locations as $location) {
            $basePath = get_template_directory();
            $path = $basePath . '/' . $location . '/' . $resolved;

            if (file_exists($path)) {
                return true;
            }

            // Also check child theme if applicable
            $childPath = get_stylesheet_directory();
            if ($childPath !== $basePath) {
                $path = $childPath . '/' . $location . '/' . $resolved;
                if (file_exists($path)) {
                    return true;
                }
            }
        }

        return false;
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
