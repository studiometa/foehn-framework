<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Query;

use Timber\Timber;

/**
 * Fluent query builder for WordPress posts.
 *
 * Accumulates WP_Query parameters and delegates to Timber::get_posts().
 * All filtering methods are null-safe (no-op when receiving empty/null values).
 */
final class PostQueryBuilder
{
    /** @var array<string, mixed> */
    private array $params = [];

    /**
     * @param string $postType The post type to query
     */
    public function __construct(string $postType)
    {
        $this->params['post_type'] = $postType;
        $this->params['post_status'] = 'publish';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pagination & Limits
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set the maximum number of posts to return.
     *
     * @param int $limit Use -1 for no limit
     */
    public function limit(int $limit): self
    {
        $this->params['posts_per_page'] = $limit;

        return $this;
    }

    /**
     * Set the number of posts to skip.
     */
    public function offset(int $offset): self
    {
        $this->params['offset'] = $offset;

        return $this;
    }

    /**
     * Set the page number for pagination.
     *
     * No-op if $page <= 0.
     */
    public function page(int $page): self
    {
        if ($page <= 0) {
            return $this;
        }

        $this->params['paged'] = $page;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ordering
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set the order field and direction.
     *
     * @param string $field WP_Query orderby value (date, title, menu_order, rand, etc.)
     * @param string $order 'ASC' or 'DESC'
     */
    public function orderBy(string $field, string $order = 'DESC'): self
    {
        $this->params['orderby'] = $field;
        $this->params['order'] = $order;

        return $this;
    }

    /**
     * Order by a meta field.
     *
     * @param string $key Meta key to order by
     * @param string $order 'ASC' or 'DESC'
     * @param bool $numeric Whether to treat as numeric (NUMERIC) or string (default)
     */
    public function orderByMeta(string $key, string $order = 'DESC', bool $numeric = false): self
    {
        $this->params['meta_key'] = $key;
        $this->params['orderby'] = $numeric ? 'meta_value_num' : 'meta_value';
        $this->params['order'] = $order;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: Status
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set the post status(es) to query.
     *
     * @param string|string[] $status
     */
    public function status(string|array $status): self
    {
        $this->params['post_status'] = $status;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: IDs
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Include only specific post IDs.
     *
     * No-op if no IDs provided.
     */
    public function include(int ...$ids): self
    {
        if ($ids === []) {
            return $this;
        }

        /** @var list<int> $existing */
        $existing = $this->params['post__in'] ?? [];
        $this->params['post__in'] = [...$existing, ...$ids];

        return $this;
    }

    /**
     * Exclude specific post IDs.
     *
     * No-op if no IDs provided.
     */
    public function exclude(int ...$ids): self
    {
        if ($ids === []) {
            return $this;
        }

        /** @var list<int> $existing */
        $existing = $this->params['post__not_in'] ?? [];
        $this->params['post__not_in'] = [...$existing, ...$ids];

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: Taxonomy
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filter by taxonomy terms.
     *
     * No-op if $terms is null, empty string, or empty array.
     *
     * @param string $taxonomy Taxonomy name
     * @param string|int|string[]|int[]|null $terms Term(s) to filter by
     * @param string $field Field to match: 'slug', 'term_id', 'name', or 'term_taxonomy_id'
     * @param string $operator 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS'
     */
    public function whereTax(
        string $taxonomy,
        string|int|array|null $terms,
        string $field = 'slug',
        string $operator = 'IN',
    ): self {
        if ($terms === null || $terms === '' || $terms === []) {
            return $this;
        }

        $this->params['tax_query'] ??= [];
        $this->params['tax_query'][] = [
            'taxonomy' => $taxonomy,
            'field' => $field,
            'terms' => $terms,
            'operator' => $operator,
        ];

        return $this;
    }

    /**
     * Set the relation for multiple taxonomy queries.
     *
     * @param string $relation 'AND' or 'OR'
     */
    public function taxRelation(string $relation): self
    {
        $this->params['tax_query'] ??= [];
        $this->params['tax_query']['relation'] = $relation;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: Meta
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filter by meta field.
     *
     * @param string $key Meta key
     * @param mixed $value Value to compare
     * @param string $compare Comparison operator: '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS'
     * @param string|null $type Value type: 'NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'
     */
    public function whereMeta(string $key, mixed $value, string $compare = '=', ?string $type = null): self
    {
        $clause = [
            'key' => $key,
            'value' => $value,
            'compare' => $compare,
        ];

        if ($type !== null) {
            $clause['type'] = $type;
        }

        $this->params['meta_query'] ??= [];
        $this->params['meta_query'][] = $clause;

        return $this;
    }

    /**
     * Set the relation for multiple meta queries.
     *
     * @param string $relation 'AND' or 'OR'
     */
    public function metaRelation(string $relation): self
    {
        $this->params['meta_query'] ??= [];
        $this->params['meta_query']['relation'] = $relation;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: Search
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Search posts by keyword.
     *
     * No-op if $terms is empty.
     */
    public function search(string $terms): self
    {
        if ($terms === '') {
            return $this;
        }

        $this->params['s'] = $terms;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: Author
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filter by author ID.
     */
    public function byAuthor(int $authorId): self
    {
        $this->params['author'] = $authorId;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: Date
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filter by date using WP_Query date_query format.
     *
     * @param array<string, mixed> $dateQuery
     * @see https://developer.wordpress.org/reference/classes/wp_date_query/
     */
    public function dateQuery(array $dateQuery): self
    {
        $this->params['date_query'] = $dateQuery;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering: Parent
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filter by parent post ID.
     *
     * @param int $parentId Parent post ID (0 for top-level posts)
     */
    public function parent(int $parentId): self
    {
        $this->params['post_parent'] = $parentId;

        return $this;
    }

    /**
     * Filter by parent post IDs.
     *
     * No-op if no IDs provided.
     */
    public function parentIn(int ...$parentIds): self
    {
        if ($parentIds === []) {
            return $this;
        }

        $this->params['post_parent__in'] = $parentIds;

        return $this;
    }

    /**
     * Exclude posts with specific parent IDs.
     *
     * No-op if no IDs provided.
     */
    public function parentNotIn(int ...$parentIds): self
    {
        if ($parentIds === []) {
            return $this;
        }

        $this->params['post_parent__not_in'] = $parentIds;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Escape Hatch
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set any WP_Query parameter directly.
     */
    public function set(string $key, mixed $value): self
    {
        $this->params[$key] = $value;

        return $this;
    }

    /**
     * Merge raw WP_Query args.
     *
     * @param array<string, mixed> $args
     */
    public function merge(array $args): self
    {
        $this->params = array_merge($this->params, $args);

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Execution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Execute the query and return posts.
     *
     * @return list<\Timber\Post>
     */
    public function get(): array
    {
        /** @var list<\Timber\Post> */
        return Timber::get_posts($this->params);
    }

    /**
     * Execute the query and return the first post.
     *
     * @return \Timber\Post|null
     */
    public function first(): mixed
    {
        $this->params['posts_per_page'] = 1;
        $results = $this->get();

        return $results[0] ?? null;
    }

    /**
     * Count matching posts without fetching them.
     */
    public function count(): int
    {
        $query = new \WP_Query([
            ...$this->params,
            'fields' => 'ids',
            'no_found_rows' => false,
        ]);

        return $query->found_posts;
    }

    /**
     * Check if any matching posts exist.
     */
    public function exists(): bool
    {
        $query = new \WP_Query([
            ...$this->params,
            'fields' => 'ids',
            'posts_per_page' => 1,
        ]);

        return $query->post_count > 0;
    }

    /**
     * Get the raw WP_Query parameters (for debugging).
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->params;
    }
}
