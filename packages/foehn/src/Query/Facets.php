<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Query;

use Studiometa\Foehn\Helpers\WP;
use WP_Query;
use WP_Term;

/**
 * The options a filter UI can offer, counted against the view the visitor is looking at.
 *
 * A filter lists every term. A facet says how many results each term would give and
 * which ones would give none, which is the difference between a control a visitor can
 * use and one they have to guess at.
 *
 * **Each facet is counted with its own filter removed.** This is the part that is easy
 * to get wrong, and getting it wrong is worse than having no counts: with the filter
 * left in, selecting "still life" makes every other series report zero, so a visitor
 * who wants two series can never pick the second one. Counts for `project_category`
 * therefore come from the current query minus its `project_category` constraint, while
 * every other filter on the page still applies.
 *
 * **The whole filtered set, not the current page.** Deriving options from `$posts`
 * counts the ten posts that happen to be on screen, so the list of filters changes as
 * the visitor pages through them — and a term that exists only on page three is
 * invisible on page one.
 *
 * The cost is one extra query for the post ids and one grouped count per facet, paid at
 * render. That is affordable here because the page cache stores the rendered HTML: the
 * count is paid once per stored page rather than once per visitor. On a page that is not
 * cached it is paid every time, which is the trade to know about.
 */
final readonly class Facets
{
    /**
     * The point at which counting stops being worth it.
     *
     * The count is one `IN (…)` over the filtered ids, so its cost follows the size of
     * the result set rather than the number of terms. Past this, options come back
     * uncounted rather than slowly — see {@see FacetOption::$count}.
     */
    public const MAX_COUNTED_POSTS = 2000;

    /**
     * The suffixes a taxonomy's constraints can arrive under.
     *
     * The bare query var is WordPress's own. The rest are the operators
     * {@see \Studiometa\Foehn\Hooks\QueryFiltersHook} adds, and they have to be stripped
     * together: a facet counted with `genre` removed but `genre__and` left in place is a
     * facet counted against a filter it was supposed to ignore.
     *
     * @var list<string>
     */
    private const OPERATOR_SUFFIXES = ['__in', '__and', '__not_in', '__exists'];

    /**
     * The options for one taxonomy, in the order `get_terms()` returns them.
     *
     * @param string $taxonomy A registered taxonomy name.
     * @param WP_Query|null $query The view to count against. Defaults to the main query.
     * @return list<FacetOption>
     */
    public function for(string $taxonomy, ?WP_Query $query = null): array
    {
        // `WP::query()` rather than the global itself: one place in the framework knows
        // where WordPress keeps its state.
        $query ??= WP::query();

        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);

        if (!is_array($terms) || $terms === []) {
            return [];
        }

        $selected = $this->selectedSlugs($query, $taxonomy);
        $counts = $this->counts($taxonomy, $query);

        $options = [];

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $options[] = new FacetOption(
                term: $term,
                count: $counts === null ? null : $counts[$term->term_id] ?? 0,
                active: in_array($term->slug, $selected, true),
            );
        }

        return $options;
    }

    /**
     * Posts per term for the current view minus this taxonomy's own filter, or null.
     *
     * @return array<int, int>|null
     */
    private function counts(string $taxonomy, WP_Query $query): ?array
    {
        $ids = $this->postIds($query, $taxonomy);

        if ($ids === null) {
            return null;
        }

        if ($ids === []) {
            return [];
        }

        $wpdb = WP::db();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = $wpdb->prepare(
            // One grouped count rather than a query per term: the number of terms does
            // not change the cost, only the size of the result set does.
            "SELECT tt.term_id AS term_id, COUNT(tr.object_id) AS matched
             FROM {$wpdb->term_relationships} tr
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tt.taxonomy = %s AND tr.object_id IN ({$placeholders})
             GROUP BY tt.term_id",
            $taxonomy,
            ...$ids,
        );

        $counts = [];

        foreach ($wpdb->get_results($sql) as $row) {
            $counts[(int) ($row->term_id ?? 0)] = (int) ($row->matched ?? 0);
        }

        return $counts;
    }

    /**
     * The ids of every post in the view, with this taxonomy's constraint lifted.
     *
     * Null when there are more than {@see Facets::MAX_COUNTED_POSTS} of them.
     *
     * @return list<int>|null
     */
    private function postIds(WP_Query $query, string $taxonomy): ?array
    {
        $vars = $this->varsWithout($query, $taxonomy);

        // Every post the filters allow, not the page being shown. `no_found_rows`
        // because nothing here pages, and `fields => ids` because nothing here needs a
        // post — both keep this to the one query it has to be.
        $vars['posts_per_page'] = self::MAX_COUNTED_POSTS + 1;
        $vars['fields'] = 'ids';
        $vars['no_found_rows'] = true;
        $vars['ignore_sticky_posts'] = true;
        unset($vars['paged'], $vars['page'], $vars['offset']);

        // A fresh WP_Query rather than the main one, so `is_main_query()` is false and
        // the hooks that apply URL filters do not apply them twice — the vars this
        // carries have been through them already.
        $ids = array_values(array_map('intval', new WP_Query($vars)->posts));

        return count($ids) > self::MAX_COUNTED_POSTS ? null : $ids;
    }

    /**
     * The query's vars with everything constraining this taxonomy taken out.
     *
     * @return array<string, mixed>
     */
    private function varsWithout(WP_Query $query, string $taxonomy): array
    {
        $vars = $query->query_vars;

        // A taxonomy may be registered under a query var that is not its name, and
        // `get_taxonomy()` answers `false` for one that is not registered at all — which
        // is a facet for a taxonomy that does not exist, so the name is all there is.
        $object = get_taxonomy($taxonomy);
        $queryVar = is_object($object) ? $object->query_var : null;

        foreach ([is_string($queryVar) && $queryVar !== '' ? $queryVar : $taxonomy, $taxonomy] as $name) {
            unset($vars[$name]);
        }

        foreach (self::OPERATOR_SUFFIXES as $suffix) {
            unset($vars[$taxonomy . $suffix]);
        }

        // The pair `parse_tax_query()` leaves behind, and the reason the first version of
        // this counted nothing: WordPress does not write its parsed `tax_query` back into
        // `query_vars`, it records the queried object as `taxonomy` + `term` — and
        // rebuilds the constraint from those on the next run. A clone that keeps them is
        // a clone still filtered by the facet it is counting.
        if (($vars['taxonomy'] ?? null) === $taxonomy) {
            unset($vars['taxonomy'], $vars['term']);
        }

        // The parsed form, which is where WordPress and QueryFiltersHook both end up.
        $taxQuery = $vars['tax_query'] ?? null;

        if (is_array($taxQuery)) {
            // Keys are kept, because one of them may be `relation` rather than a clause.
            // Renumbering turns `['relation' => 'AND']` into `[0 => 'AND']`, which is a
            // tax_query WordPress reads as a clause and cannot make sense of.
            $kept = [];

            foreach ($taxQuery as $key => $clause) {
                if (is_array($clause) && ($clause['taxonomy'] ?? null) === $taxonomy) {
                    continue;
                }

                $kept[$key] = $clause;
            }

            // A relation with nothing left to relate constrains nothing.
            $clauses = array_filter($kept, static fn(mixed $clause): bool => is_array($clause));

            $vars['tax_query'] = $kept;

            if ($clauses === []) {
                unset($vars['tax_query']);
            }
        }

        return $vars;
    }

    /**
     * The slugs this taxonomy is currently filtered by, in either spelling.
     *
     * @return list<string>
     */
    private function selectedSlugs(WP_Query $query, string $taxonomy): array
    {
        $slugs = [];

        foreach ([
            $taxonomy,
            ...array_map(static fn(string $s): string => $taxonomy . $s, self::OPERATOR_SUFFIXES),
        ] as $name) {
            $value = $query->get($name);

            if (is_array($value)) {
                $slugs = [...$slugs, ...array_map('strval', $value)];

                continue;
            }

            if (is_string($value) && $value !== '') {
                $slugs = [...$slugs, ...array_map('trim', explode(',', $value))];
            }
        }

        $taxQuery = $query->query_vars['tax_query'] ?? null;

        if (is_array($taxQuery)) {
            foreach ($taxQuery as $clause) {
                if (!is_array($clause) || ($clause['taxonomy'] ?? null) !== $taxonomy) {
                    continue;
                }

                // A clause may name one term or several, and WordPress accepts both.
                $terms = $clause['terms'] ?? [];
                $slugs = [...$slugs, ...array_map('strval', is_array($terms) ? $terms : [$terms])];
            }
        }

        return array_values(array_unique(array_filter($slugs, static fn(string $s): bool => $s !== '')));
    }
}
