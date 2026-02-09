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
     * Check if a taxonomy is allowed.
     */
    public function hasTaxonomy(string $taxonomy): bool
    {
        return isset($this->taxonomies[$taxonomy]);
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
        if (!isset($this->publicVars[$var])) {
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
