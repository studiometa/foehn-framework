<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for URL query manipulation.
 *
 * Provides helper functions for reading URL parameters and building URLs
 * with modified query strings. Wraps WordPress `add_query_arg` and
 * `remove_query_arg` functions.
 *
 * Reading parameters:
 * ```twig
 * {{ query_get('category') }}
 * {{ query_get('page', 1) }}              {# with default #}
 * {{ query_has('category') }}
 * {{ query_has('category', 'news') }}     {# has specific value #}
 * {{ query_contains('tags', 'php') }}     {# value in array #}
 * {{ query_all() }}                       {# all params as array #}
 * ```
 *
 * URL building:
 * ```twig
 * {{ query_url({category: 'news'}) }}
 * {{ query_url_without('category') }}
 * {{ query_url_without(['category', 'page']) }}
 * {{ query_url_toggle('tags', 'php') }}
 * {{ query_url_clear() }}
 * ```
 *
 * Form helper:
 * ```twig
 * {{ query_hidden_inputs() | raw }}
 * {{ query_hidden_inputs(exclude=['s']) | raw }}
 * ```
 */
#[AsTwigExtension]
final class QueryExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'foehn_query';
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('query_get', $this->get(...)),
            new TwigFunction('query_has', $this->has(...)),
            new TwigFunction('query_contains', $this->contains(...)),
            new TwigFunction('query_all', $this->all(...)),
            new TwigFunction('query_url', $this->url(...)),
            new TwigFunction('query_url_without', $this->urlWithout(...)),
            new TwigFunction('query_url_toggle', $this->urlToggle(...)),
            new TwigFunction('query_url_clear', $this->urlClear(...)),
            new TwigFunction('query_hidden_inputs', $this->hiddenInputs(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Get a query parameter value.
     *
     * @param string $key Parameter name
     * @param mixed $default Default value if not set
     * @return mixed Parameter value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $params = $this->getQueryParams();
        $value = $params[$key] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return $default;
        }

        return $value;
    }

    /**
     * Check if a query parameter exists (optionally with a specific value).
     *
     * @param string $key Parameter name
     * @param mixed $value Optional value to check for
     * @return bool True if parameter exists (and matches value if provided)
     */
    public function has(string $key, mixed $value = null): bool
    {
        $current = $this->get($key);

        if ($current === null) {
            return false;
        }

        if ($value === null) {
            return true;
        }

        return $this->contains($key, $value);
    }

    /**
     * Check if a query parameter contains a specific value (for array parameters).
     *
     * @param string $key Parameter name
     * @param mixed $value Value to search for
     * @return bool True if value is in the parameter
     */
    public function contains(string $key, mixed $value): bool
    {
        $current = $this->get($key);

        if ($current === null) {
            return false;
        }

        if (is_array($current)) {
            return in_array((string) $value, array_map('strval', $current), true);
        }

        return (string) $current === (string) $value;
    }

    /**
     * Get all query parameters (excluding empty values).
     *
     * @return array<string, mixed> Query parameters
     */
    public function all(): array
    {
        return array_filter($this->getQueryParams(), static fn(mixed $v): bool => $v !== '' && $v !== []);
    }

    /**
     * Build a URL with additional/modified query parameters.
     *
     * @param array<string, mixed> $params Parameters to add/modify
     * @return string URL with modified query string
     */
    public function url(array $params): string
    {
        return $this->escUrl($this->addQueryArg($params));
    }

    /**
     * Build a URL with query parameters removed.
     *
     * @param string|list<string> $keys Parameter(s) to remove
     * @return string URL without specified parameters
     */
    public function urlWithout(string|array $keys): string
    {
        return $this->escUrl($this->removeQueryArg((array) $keys));
    }

    /**
     * Build a URL with a value toggled in a parameter.
     *
     * If the value exists, it's removed. If it doesn't exist, it's added.
     *
     * @param string $key Parameter name
     * @param mixed $value Value to toggle
     * @return string URL with toggled value
     */
    public function urlToggle(string $key, mixed $value): string
    {
        $value = (string) $value;

        if ($this->contains($key, $value)) {
            // Remove value
            $current = $this->get($key, []);
            $current = is_array($current) ? $current : [$current];
            $current = array_filter($current, static fn(mixed $v): bool => (string) $v !== $value);

            if ($current === []) {
                return $this->urlWithout($key);
            }

            return $this->escUrl($this->addQueryArg([$key => array_values($current)]));
        }

        // Add value
        $current = $this->get($key, []);
        if (!is_array($current)) {
            $current = $current !== null ? [$current] : [];
        }
        $current[] = $value;

        return $this->escUrl($this->addQueryArg([$key => array_values($current)]));
    }

    /**
     * Build a URL with all query parameters removed.
     *
     * @return string Base URL without query string
     */
    public function urlClear(): string
    {
        $uri = $this->getRequestUri();

        return $this->escUrl(strtok($uri, '?') ?: '/');
    }

    /**
     * Generate hidden input fields for current query parameters.
     *
     * Useful for preserving filters in forms.
     *
     * @param list<string> $exclude Parameters to exclude
     * @return string HTML hidden inputs
     */
    public function hiddenInputs(array $exclude = []): string
    {
        $html = '';

        foreach ($this->all() as $key => $value) {
            if (in_array($key, $exclude, true)) {
                continue;
            }

            $values = is_array($value) ? $value : [$value];
            $name = is_array($value) ? "{$key}[]" : $key;

            foreach ($values as $v) {
                $html .= sprintf(
                    '<input type="hidden" name="%s" value="%s">',
                    $this->escAttr($name),
                    $this->escAttr((string) $v),
                );
            }
        }

        return $html;
    }

    /**
     * Get query parameters from request.
     *
     * @return array<string, mixed>
     */
    protected function getQueryParams(): array
    {
        /** @var array<string, mixed> */
        return $_GET;
    }

    /**
     * Get request URI.
     */
    protected function getRequestUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Add query arguments to URL.
     *
     * @param array<string, mixed> $args
     */
    protected function addQueryArg(array $args): string
    {
        if (function_exists('add_query_arg')) {
            return add_query_arg($args);
        }

        // Fallback for non-WordPress context (tests)
        $uri = $this->getRequestUri();
        $parsed = parse_url($uri);
        $path = is_array($parsed) && isset($parsed['path']) ? $parsed['path'] : '/';
        $queryString = is_array($parsed) && isset($parsed['query']) ? $parsed['query'] : '';
        $existing = [];
        parse_str($queryString, $existing);

        $merged = array_merge($existing, $args);
        $query = http_build_query($merged);

        return $query !== '' ? "{$path}?{$query}" : $path;
    }

    /**
     * Remove query arguments from URL.
     *
     * @param list<string> $keys
     */
    protected function removeQueryArg(array $keys): string
    {
        if (function_exists('remove_query_arg')) {
            return remove_query_arg($keys);
        }

        // Fallback for non-WordPress context (tests)
        $uri = $this->getRequestUri();
        $parsed = parse_url($uri);
        $path = is_array($parsed) && isset($parsed['path']) ? $parsed['path'] : '/';
        $queryString = is_array($parsed) && isset($parsed['query']) ? $parsed['query'] : '';
        $existing = [];
        parse_str($queryString, $existing);

        foreach ($keys as $key) {
            unset($existing[$key]);
        }

        $query = http_build_query($existing);

        return $query !== '' ? "{$path}?{$query}" : $path;
    }

    /**
     * Escape URL.
     */
    protected function escUrl(string $url): string
    {
        if (function_exists('esc_url')) {
            return esc_url($url);
        }

        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Escape attribute value.
     */
    protected function escAttr(string $value): string
    {
        if (function_exists('esc_attr')) {
            return esc_attr($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
