<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views;

use Studiometa\Foehn\Contracts\ContextProviderInterface;

/**
 * Registry for context providers.
 *
 * Manages the registration and execution of context providers
 * that add data to matching templates.
 */
final class ContextProviderRegistry
{
    /**
     * @var array<string, array{provider: ContextProviderInterface, priority: int}[]>
     */
    private array $providers = [];

    /**
     * @var array<string, array{provider: ContextProviderInterface, priority: int}[]>
     */
    private array $wildcardProviders = [];

    /**
     * Register a provider for specific templates.
     *
     * @param string[] $templates Template patterns (supports wildcards with *)
     * @param ContextProviderInterface $provider The provider instance
     * @param int $priority Execution priority (lower = earlier)
     */
    public function register(array $templates, ContextProviderInterface $provider, int $priority = 10): void
    {
        foreach ($templates as $template) {
            $entry = ['provider' => $provider, 'priority' => $priority];

            if (str_contains($template, '*')) {
                $this->wildcardProviders[$template][] = $entry;

                continue;
            }

            $this->providers[$template][] = $entry;
        }
    }

    /**
     * Provide context for a template by running all matching providers.
     *
     * @param string $template Template name being rendered
     * @param array<string, mixed> $context Current context
     * @return array<string, mixed> Modified context
     */
    public function provide(string $template, array $context): array
    {
        $matchingProviders = $this->getMatchingProviders($template);

        // Sort by priority
        usort($matchingProviders, static fn($a, $b) => $a['priority'] <=> $b['priority']);

        // Run each provider
        foreach ($matchingProviders as $entry) {
            $context = $entry['provider']->provide($context);
        }

        return $context;
    }

    /**
     * Get all providers that match a template.
     *
     * @param string $template Template name
     * @return array{provider: ContextProviderInterface, priority: int}[]
     */
    private function getMatchingProviders(string $template): array
    {
        $matching = [];

        // Exact matches
        if (isset($this->providers[$template])) {
            $matching = [...$matching, ...$this->providers[$template]];
        }

        // Wildcard matches
        foreach ($this->wildcardProviders as $pattern => $providers) {
            if (!$this->matchesPattern($template, $pattern)) {
                continue;
            }

            $matching = [...$matching, ...$providers];
        }

        return $matching;
    }

    /**
     * Check if a template matches a wildcard pattern.
     *
     * @param string $template Template name
     * @param string $pattern Pattern with * wildcards
     * @return bool
     */
    private function matchesPattern(string $template, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $template);
    }

    /**
     * Check if any providers are registered for a template.
     *
     * @param string $template Template name
     * @return bool
     */
    public function hasProviders(string $template): bool
    {
        return count($this->getMatchingProviders($template)) > 0;
    }

    /**
     * Get count of registered providers.
     *
     * @return int
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->providers as $providers) {
            $count += count($providers);
        }

        foreach ($this->wildcardProviders as $providers) {
            $count += count($providers);
        }

        return $count;
    }
}
