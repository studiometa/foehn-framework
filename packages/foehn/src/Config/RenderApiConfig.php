<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * Configuration for the Render API.
 *
 * Create a config file in your app directory:
 *
 * ```php
 * // app/render-api.config.php
 * use Studiometa\Foehn\Config\RenderApiConfig;
 *
 * return new RenderApiConfig(
 *     templates: ['partials/*', 'components/*'],
 * );
 * ```
 */
final readonly class RenderApiConfig
{
    /**
     * @param list<string> $templates Allowed template patterns (supports * wildcard)
     * @param int $cacheMaxAge Cache-Control max-age in seconds (0 to disable)
     * @param bool $debug When true, error messages include exception details
     */
    public function __construct(
        public array $templates = [],
        public int $cacheMaxAge = 0,
        public bool $debug = false,
    ) {}

    /**
     * Check if a template path is allowed.
     */
    public function isTemplateAllowed(string $template): bool
    {
        if ($this->templates === []) {
            return false;
        }

        foreach ($this->templates as $pattern) {
            if (!$this->matchPattern($template, $pattern)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Match a template against a pattern with wildcard support.
     */
    private function matchPattern(string $template, string $pattern): bool
    {
        // Escape regex metacharacters, then convert * to pattern
        // Use # as delimiter to avoid escaping slashes
        $regex = preg_quote($pattern, '#');
        $regex = str_replace('\*', '[^/]*', $regex);

        return (bool) preg_match('#^' . $regex . '$#', $template);
    }
}
