<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search\Query;

use Studiometa\Foehn\Helpers\WP;
use Studiometa\Foehn\Search\SearchDocuments;
use WP_Post;
use WP_Query;

/**
 * The results of the search page grouped by post type, for a template that shows
 * each group with its best results and a "see all" link.
 *
 * The post type is a facet like a taxonomy: the groups count the results of every
 * searchable post type, with the other filters of the page still applied.
 */
final readonly class SearchGroups
{
    public function __construct(
        private SearchDocuments $documents,
    ) {}

    /**
     * @param WP_Query|null $query The search query. Defaults to the main query.
     * @param int $perGroup The number of results in each group
     * @return list<SearchGroup> In the order of the most relevant result of each group
     */
    public function for(?WP_Query $query = null, int $perGroup = 3): array
    {
        $query ??= WP::query();
        $vars = $query->query_vars;

        if (($vars[SearchQuery::QUERY_VAR] ?? false) !== true) {
            return [];
        }

        unset($vars['paged'], $vars['page']);
        $vars['post_type'] = $this->documents->postTypes();
        $vars['fields'] = 'ids';
        $vars['posts_per_page'] = -1;
        $vars['no_found_rows'] = true;

        $ids = array_map(intval(...), new WP_Query($vars)->posts);

        if ($ids === []) {
            return [];
        }

        /** @var array<string, list<int>> $byType */
        $byType = [];

        foreach ($this->postTypes($ids) as $id => $postType) {
            $byType[$postType][] = $id;
        }

        $groups = [];

        foreach ($byType as $postType => $typeIds) {
            $object = get_post_type_object($postType);
            $label = is_object($object) ? (string) ($object->labels->name ?? $postType) : $postType;

            $groups[] = new SearchGroup(
                postType: $postType,
                label: $label,
                count: count($typeIds),
                posts: array_values(array_filter(
                    array_map(get_post(...), array_slice($typeIds, 0, $perGroup)),
                    static fn(mixed $post): bool => $post instanceof WP_Post,
                )),
                url: remove_query_arg('paged', add_query_arg('post_type', $postType)),
            );
        }

        return $groups;
    }

    /**
     * The post type of each id, in the order of the ids.
     *
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function postTypes(array $ids): array
    {
        $wpdb = WP::db();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $types = [];

        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_type FROM {$wpdb->posts} WHERE ID IN ({$placeholders})",
            ...$ids,
        )) as $row) {
            $types[(int) $row->ID] = (string) $row->post_type;
        }

        $ordered = [];

        foreach ($ids as $id) {
            $type = $types[$id] ?? null;

            if ($type !== null) {
                $ordered[$id] = $type;
            }
        }

        return $ordered;
    }
}
