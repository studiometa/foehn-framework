<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Views\Sections\SectionRequest;
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

        // A comma-separated string is the framework's own multi-value format — it is
        // what `?genre=rock,jazz` means to WP_Query, and the one spelling nginx can key.
        // Reading it as a single opaque value made `query_contains()` answer false for
        // every checkbox on a page reached by a shared filter link.
        return in_array((string) $value, self::split((string) $current), true);
    }

    /**
     * A query parameter's values, whichever of the two spellings carried them.
     *
     * @return list<string>
     */
    private static function split(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn(string $part): bool => $part !== '',
        ));
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
            return $this->urlToggleRemove($key, $value);
        }

        return $this->urlToggleAdd($key, $value);
    }

    /**
     * Remove a value from a query parameter.
     */
    private function urlToggleRemove(string $key, string $value): string
    {
        $current = array_values(array_filter($this->values($key), static fn(string $v): bool => $v !== $value));

        if ($current === []) {
            return $this->urlWithout($key);
        }

        return $this->multiValueUrl($key, $current);
    }

    /**
     * Add a value to a query parameter.
     */
    private function urlToggleAdd(string $key, string $value): string
    {
        return $this->multiValueUrl($key, [...$this->values($key), $value]);
    }

    /**
     * A URL carrying several values for one parameter, joined by a literal comma.
     *
     * `add_query_arg()` percent-encodes the separator into `%2C`, which is correct and
     * useless here: a comma is a sub-delimiter a query string may carry as itself, no
     * reader decodes one back, and `%` is not a character the page cache will put in a
     * filename. Encoded, every one of these links is a cache bypass — which is a slow
     * page rather than an error, so nobody would find it.
     *
     * @param list<string> $values
     */
    private function multiValueUrl(string $key, array $values): string
    {
        $url = $this->escUrl($this->addQueryArg([$key => implode(',', $values)]));

        return str_replace('%2C', ',', $url);
    }

    /**
     * The current values of a parameter, from either spelling, as a plain list.
     *
     * @return list<string>
     */
    private function values(string $key): array
    {
        $current = $this->get($key);

        if ($current === null) {
            return [];
        }

        if (is_array($current)) {
            return array_values(array_map('strval', $current));
        }

        return self::split((string) $current);
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
        /** @var array<string, mixed> $params */
        $params = $_GET;
        unset($params[SectionRequest::PARAMETER]);

        return $params;
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
        unset($args[SectionRequest::PARAMETER]);
        $uri = remove_query_arg(SectionRequest::PARAMETER, $this->getRequestUri());

        return add_query_arg($args, $uri);
    }

    /**
     * Remove query arguments from URL.
     *
     * @param list<string> $keys
     */
    protected function removeQueryArg(array $keys): string
    {
        $keys[] = SectionRequest::PARAMETER;

        return remove_query_arg(array_values(array_unique($keys)), $this->getRequestUri());
    }

    /**
     * Escape URL.
     */
    protected function escUrl(string $url): string
    {
        return esc_url($url);
    }

    /**
     * Escape attribute value.
     */
    protected function escAttr(string $value): string
    {
        return esc_attr($value);
    }
}
