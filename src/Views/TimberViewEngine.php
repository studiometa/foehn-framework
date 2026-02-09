<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views;

use RuntimeException;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
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
    public function render(string $template, array $context = []): string
    {
        $resolved = $this->resolveTemplate($template);

        // Merge Timber's global context first (site, theme, user, etc.),
        // then shared data, then context (context wins)
        $context = array_merge(Timber::context_global(), $this->shared, $context);

        // Apply context providers
        $context = $this->contextProviders->provide($template, $context);

        $result = Timber::compile($resolved, $context);

        if ($result === false) {
            throw new RuntimeException("Failed to render template: {$template}");
        }

        return is_string($result) ? $result : '';
    }

    /**
     * @inheritDoc
     */
    public function renderFirst(array $templates, array $context = []): string
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
