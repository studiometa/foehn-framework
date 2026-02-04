<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * Interface for view rendering engines.
 *
 * This abstraction allows switching between different template engines
 * (Timber/Twig, Blade, Tempest View) while maintaining a consistent API.
 */
interface ViewEngineInterface
{
    /**
     * Render a template with the given context.
     *
     * @param string $template Template name/path (without extension)
     * @param array<string, mixed> $context Variables to pass to the template
     * @return string Rendered HTML
     */
    public function render(string $template, array $context = []): string;

    /**
     * Render the first existing template from a list.
     *
     * Useful for WordPress template hierarchy fallbacks.
     *
     * @param string[] $templates List of template names to try
     * @param array<string, mixed> $context Variables to pass to the template
     * @return string Rendered HTML
     * @throws \RuntimeException If no template is found
     */
    public function renderFirst(array $templates, array $context = []): string;

    /**
     * Check if a template exists.
     *
     * @param string $template Template name/path
     * @return bool
     */
    public function exists(string $template): bool;

    /**
     * Share data with all templates.
     *
     * @param string $key Variable name
     * @param mixed $value Variable value
     */
    public function share(string $key, mixed $value): void;

    /**
     * Get all shared data.
     *
     * @return array<string, mixed>
     */
    public function getShared(): array;
}
