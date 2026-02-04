<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Views;

use Studiometa\WPTempest\Contracts\ViewComposerInterface;

/**
 * Registry for view composers.
 *
 * Manages the registration and execution of view composers
 * that add data to matching templates.
 */
final class ViewComposerRegistry
{
    /**
     * @var array<string, array{composer: ViewComposerInterface, priority: int}[]>
     */
    private array $composers = [];

    /**
     * @var array<string, array{composer: ViewComposerInterface, priority: int}[]>
     */
    private array $wildcardComposers = [];

    /**
     * Register a composer for specific templates.
     *
     * @param string[] $templates Template patterns (supports wildcards with *)
     * @param ViewComposerInterface $composer The composer instance
     * @param int $priority Execution priority (lower = earlier)
     */
    public function register(array $templates, ViewComposerInterface $composer, int $priority = 10): void
    {
        foreach ($templates as $template) {
            $entry = ['composer' => $composer, 'priority' => $priority];

            if (str_contains($template, '*')) {
                $this->wildcardComposers[$template][] = $entry;

                continue;
            }

            $this->composers[$template][] = $entry;
        }
    }

    /**
     * Compose context for a template by running all matching composers.
     *
     * @param string $template Template name being rendered
     * @param array<string, mixed> $context Current context
     * @return array<string, mixed> Modified context
     */
    public function compose(string $template, array $context): array
    {
        $matchingComposers = $this->getMatchingComposers($template);

        // Sort by priority
        usort($matchingComposers, static fn($a, $b) => $a['priority'] <=> $b['priority']);

        // Run each composer
        foreach ($matchingComposers as $entry) {
            $context = $entry['composer']->compose($context);
        }

        return $context;
    }

    /**
     * Get all composers that match a template.
     *
     * @param string $template Template name
     * @return array{composer: ViewComposerInterface, priority: int}[]
     */
    private function getMatchingComposers(string $template): array
    {
        $matching = [];

        // Exact matches
        if (isset($this->composers[$template])) {
            $matching = [...$matching, ...$this->composers[$template]];
        }

        // Wildcard matches
        foreach ($this->wildcardComposers as $pattern => $composers) {
            if (!$this->matchesPattern($template, $pattern)) {
                continue;
            }

            $matching = [...$matching, ...$composers];
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
     * Check if any composers are registered for a template.
     *
     * @param string $template Template name
     * @return bool
     */
    public function hasComposers(string $template): bool
    {
        return count($this->getMatchingComposers($template)) > 0;
    }

    /**
     * Get count of registered composers.
     *
     * @return int
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->composers as $composers) {
            $count += count($composers);
        }

        foreach ($this->wildcardComposers as $composers) {
            $count += count($composers);
        }

        return $count;
    }
}
