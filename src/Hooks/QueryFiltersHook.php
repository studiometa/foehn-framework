<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Config\QueryFiltersConfig;
use WP_Query;

/**
 * Opt-in hook to enable URL-based query filtering.
 *
 * This hook registers custom query vars and applies taxonomy/var filters
 * to the main WordPress query based on URL parameters.
 *
 * 1. Add this class to your hooks configuration:
 *
 * ```php
 * // functions.php
 * Kernel::boot(__DIR__, [
 *     'hooks' => [
 *         QueryFiltersHook::class,
 *     ],
 * ]);
 * ```
 *
 * 2. Create a config file to define allowed filters:
 *
 * ```php
 * // app/query-filters.config.php
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
final readonly class QueryFiltersHook
{
    public function __construct(
        private QueryFiltersConfig $config,
    ) {}

    /**
     * Register custom query vars with WordPress.
     *
     * @param list<string> $vars Existing query vars
     * @return list<string> Modified query vars
     */
    #[AsFilter('query_vars')]
    public function registerQueryVars(array $vars): array
    {
        return [...$vars, ...$this->config->getQueryVars()];
    }

    /**
     * Apply filters to the main query based on URL parameters.
     */
    #[AsAction('pre_get_posts')]
    public function applyFilters(WP_Query $query): void
    {
        if (!$query->is_main_query() || is_admin()) {
            return;
        }

        $this->applyTaxonomyFilters($query);
        $this->applyPublicVars($query);
    }

    /**
     * Apply taxonomy filters from URL parameters.
     */
    private function applyTaxonomyFilters(WP_Query $query): void
    {
        $taxQuery = [];

        foreach ($this->config->taxonomies as $taxonomy => $operators) {
            foreach ($operators as $operator) {
                $paramName = $operator === 'in' ? $taxonomy : "{$taxonomy}__{$operator}";
                $value = $query->get($paramName);

                if ($value === '' || $value === null) {
                    continue;
                }

                // Parse comma-separated values
                if (is_string($value)) {
                    $terms = array_filter(array_map('trim', explode(',', $value)));
                } elseif (is_array($value)) {
                    /** @var list<string> $terms */
                    $terms = $value;
                } else {
                    continue;
                }

                if ($terms === []) {
                    continue;
                }

                $taxQuery[] = $this->buildTaxQueryClause($taxonomy, $operator, $terms);
            }
        }

        if ($taxQuery !== []) {
            // Merge with existing tax_query
            $existingTaxQuery = $query->get('tax_query');
            if (is_array($existingTaxQuery) && $existingTaxQuery !== []) {
                $taxQuery = array_merge($existingTaxQuery, $taxQuery);
            }

            // Default relation is AND between different taxonomies
            if (!isset($taxQuery['relation'])) {
                $taxQuery['relation'] = 'AND';
            }

            $query->set('tax_query', $taxQuery);
        }
    }

    /**
     * Build a single tax_query clause.
     *
     * @param string $taxonomy Taxonomy name
     * @param string $operator Operator type (in, not_in, and, exists)
     * @param list<string> $terms Term slugs
     * @return array{taxonomy: string, field: string, terms: list<string>, operator: string}
     */
    private function buildTaxQueryClause(string $taxonomy, string $operator, array $terms): array
    {
        $wpOperator = match ($operator) {
            'in' => 'IN',
            'not_in' => 'NOT IN',
            'and' => 'AND',
            'exists' => 'EXISTS',
            default => 'IN',
        };

        return [
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $terms,
            'operator' => $wpOperator,
        ];
    }

    /**
     * Apply whitelisted public vars to the query.
     */
    private function applyPublicVars(WP_Query $query): void
    {
        foreach (array_keys($this->config->publicVars) as $var) {
            $value = $query->get($var);

            if ($value === '' || $value === null) {
                continue;
            }

            // Validate against whitelist
            if (!$this->config->validatePublicVar($var, $value)) {
                // Invalid value, reset to empty
                $query->set($var, '');

                continue;
            }

            // Value is valid and already set by WordPress
        }
    }
}
