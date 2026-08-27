<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * Configuration for URL-based query filtering.
 *
 * Extends WordPress native query handling with security allowlists
 * for custom taxonomies and private query vars.
 *
 * WordPress already handles these URL parameters natively:
 * - `cat`, `category_name` - Categories
 * - `tag` - Tags
 * - `author` - Author
 * - `s` - Search
 * - `orderby`, `order` - Sorting
 *
 * This config allows you to expose additional taxonomies and vars.
 *
 * URL format follows WordPress conventions:
 * - `?genre=rock` - IN (default)
 * - `?genre=rock,jazz` - Multiple values
 * - `?genre__not_in=classical` - NOT IN operator
 * - `?genre__and=rock,jazz` - AND operator
 *
 * Example config file (app/query-filters.config.php):
 *
 * ```php
 * use Studiometa\Foehn\Config\QueryFiltersConfig;
 *
 * return new QueryFiltersConfig(
 *     taxonomies: [
 *         'genre' => ['in', 'not_in', 'and'],
 *         'product_cat' => ['in'],
 *     ],
 *     publicVars: [
 *         'posts_per_page' => [12, 24, 48],
 *     ],
 * );
 * ```
 */
final readonly class QueryFiltersConfig
{
    /**
     * @param array<string, list<'in'|'not_in'|'and'|'exists'>> $taxonomies
     *     Map of taxonomy slug to allowed operators.
     *     - 'in': Match posts in any of the specified terms (default, uses just taxonomy name)
     *     - 'not_in': Exclude posts in specified terms (uses taxonomy__not_in)
     *     - 'and': Match posts in ALL specified terms (uses taxonomy__and)
     *     - 'exists': Match posts that have any term in taxonomy (uses taxonomy__exists)
     * @param array<string, list<scalar>|true> $publicVars
     *     Map of private query vars to make public.
     *     - Array of values: Only these values are allowed
     *     - true: Any value is allowed (use with caution)
     */
    public function __construct(
        public array $taxonomies = [],
        public array $publicVars = [],
    ) {}

    /**
     * Get all query var names that should be registered.
     *
     * @return list<string>
     */
    public function getQueryVars(): array
    {
        $vars = [];

        foreach ($this->taxonomies as $taxonomy => $operators) {
            // Base taxonomy var (for 'in' operator)
            $vars[] = $taxonomy;

            foreach ($operators as $operator) {
                if ($operator === 'in') {
                    continue;
                }

                $vars[] = "{$taxonomy}__{$operator}";
            }
        }

        foreach (array_keys($this->publicVars) as $var) {
            $vars[] = $var;
        }

        return $vars;
    }

    /**
     * The same filters, as query args the page cache can key, with a pattern each.
     *
     * A filter that is declared here and not keyed there is a filter whose every URL
     * bypasses the cache — and a bypass reads as a slow page rather than as an error, so
     * nobody finds it. {@see \Studiometa\Foehn\Config\PageCacheConfig::$queryFilters}
     * takes this and stops the two lists from being maintained twice.
     *
     * The pattern comes from the allowlist itself, which is the part worth having: the
     * cache then refuses exactly the values `QueryFiltersHook` would have rejected, and
     * the two cannot come to different conclusions about what a valid request is.
     *
     * A `publicVars` entry of `true` is deliberately **not** derived. It means any value
     * at all, and a keyed argument with no bound on its values is an unbounded number of
     * files on disk — one per string an address bar can hold. A project that wants it
     * cached names it in `cacheQueryArgs` with a pattern it has thought about.
     *
     * @return array<string, string>
     */
    public function toCacheQueryArgs(): array
    {
        $slug = '[A-Za-z0-9_-]+';
        $list = '^' . $slug . '(?:,' . $slug . ')*$';

        $args = [];

        foreach ($this->taxonomies as $taxonomy => $operators) {
            foreach ($operators as $operator) {
                $name = $operator === 'in' ? $taxonomy : "{$taxonomy}__{$operator}";

                // `exists` is a switch, not a term list.
                $args[$name] = $operator === 'exists' ? '^[01]$' : $list;
            }
        }

        foreach ($this->publicVars as $var => $allowed) {
            if ($allowed === true) {
                continue;
            }

            $values = array_map(static fn(string|int|float|bool $value): string => preg_quote(
                (string) $value,
                '#',
            ), $allowed);

            if ($values === []) {
                continue;
            }

            $args[$var] = '^(?:' . implode('|', $values) . ')$';
        }

        return $args;
    }

    /**
     * Check if a taxonomy is allowed.
     */
    public function hasTaxonomy(string $taxonomy): bool
    {
        return ($this->taxonomies[$taxonomy] ?? null) !== null;
    }

    /**
     * Check if an operator is allowed for a taxonomy.
     *
     * @param 'in'|'not_in'|'and'|'exists' $operator
     */
    public function hasOperator(string $taxonomy, string $operator): bool
    {
        if (!$this->hasTaxonomy($taxonomy)) {
            return false;
        }

        return in_array($operator, $this->taxonomies[$taxonomy], true);
    }

    /**
     * Validate a public var value.
     *
     * @return bool True if value is allowed
     */
    public function validatePublicVar(string $var, mixed $value): bool
    {
        if (($this->publicVars[$var] ?? null) === null) {
            return false;
        }

        $allowed = $this->publicVars[$var];

        // true means any value is allowed
        if ($allowed === true) {
            return true;
        }

        // Check against whitelist (cast to string for comparison since URL params are strings)
        $valueStr = (string) $value;

        foreach ($allowed as $allowedValue) {
            if ($valueStr !== (string) $allowedValue) {
                continue;
            }

            return true;
        }

        return false;
    }
}
