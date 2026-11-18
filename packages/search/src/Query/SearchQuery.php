<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Query;

use Studiometa\Foehn\Search\Languages;
use Studiometa\Foehn\Search\SearchDocuments;
use Studiometa\Foehn\Search\SearchIndex;
use WP_Query;

/**
 * The search page, answered by the index.
 *
 * The index does the text match only, and gives every matching id in order of
 * relevance. The main query gets them as `post__in`, ordered by `post__in`, and
 * WordPress does the rest as on any archive: the taxonomy filters, the language,
 * the pages, and the facet counts of {@see \Studiometa\Foehn\Query\Facets}.
 *
 * {@see \Studiometa\Foehn\Search\Discovery\SearchDocumentDiscovery} adds these methods
 * to `pre_get_posts` and `posts_search`.
 */
final readonly class SearchQuery
{
    /**
     * The query var that marks a query whose text match the index did.
     *
     * A query var, so it stays on the copies of the query that Facets makes.
     */
    public const string QUERY_VAR = 'foehn_search';

    public function __construct(
        private SearchIndex $index,
        private Languages $languages,
        private SearchDocuments $documents,
    ) {}

    public function onPreGetPosts(WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query() || !$query->is_search() || $this->documents->postTypes() === []) {
            return;
        }

        // An empty search is no text match, not every post: no result.
        $ids = $this->index->ids($this->languages->current(), (string) $query->get('s'));

        $query->set('post__in', $ids === [] ? [0] : $ids);
        $query->set('orderby', 'post__in');
        $query->set(self::QUERY_VAR, true);

        $postType = $query->get('post_type');

        if ($postType === '' || $postType === 'any' || $postType === []) {
            $query->set('post_type', $this->documents->postTypes());
        }
    }

    /**
     * No `LIKE` search of WordPress on top of the index: the ids are the match.
     */
    public function withoutLikeSearch(string $search, WP_Query $query): string
    {
        return $query->get(self::QUERY_VAR) === true ? '' : $search;
    }
}
