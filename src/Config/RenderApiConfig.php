<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * Configuration for the Render API.
 */
final readonly class RenderApiConfig
{
    /**
     * @param bool $enabled Whether the render API is enabled
     * @param list<string> $templates Allowed template patterns (supports * wildcard)
     */
    public function __construct(
        public bool $enabled = true,
        public array $templates = [],
    ) {}

    /**
     * Create config from array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        /** @var list<string> $templates */
        $templates = $config['templates'] ?? [];

        return new self(enabled: $config['enabled'] ?? true, templates: $templates);
    }

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
        // Convert glob pattern to regex
        $regex = str_replace(['*', '/'], ['[^/]*', '\/'], $pattern);

        return (bool) preg_match('/^' . $regex . '$/', $template);
    }
}
