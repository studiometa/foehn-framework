<?php

declare(strict_types=1);

/**
 * The search package with Polylang, set up and read for `SearchTest`, through
 * `wp eval-file`.
 *
 * Each run registers the search document of `search-document.php`.
 *
 * - `installed`: 1 when studiometa/foehn-search is installed, else 0.
 * - `setup`: English (default) and French, three posts with a category each, and the
 *   indexes rebuilt. Prints the number of documents of each language, as JSON.
 * - `search <lang> <s> [category]`: the main search query of the front end, as JSON:
 *   the titles in order, and the count of each smoke category with its own filter lifted.
 * - `rename <slug> <title>`: a new title, saved as an editor does. The index follows
 *   at shutdown.
 * - `teardown`: removes the posts, the categories, the languages and the indexes.
 *
 * Prints one line, because `Site::wp()` hands back the last line.
 */

use Studiometa\Foehn\Query\Facets;
use Studiometa\Foehn\Search\Config\SearchConfig;
use Studiometa\Foehn\Search\Discovery\SearchDocumentDiscovery;
use Studiometa\Foehn\Search\Indexer;

use function Tempest\Container\get;

/** @var list<string> $args */
if (($args[0] ?? '') === 'installed') {
    echo class_exists(SearchDocumentDiscovery::class) && function_exists('PLL') ? 1 : 0;

    return;
}

require __DIR__ . '/search-document.php';

$model = PLL()->model;
$posts = [
    'smoke-search-starter' => ['Smoke sourdough starter', 'en', 'smoke-bread'],
    'smoke-search-crackers' => ['Smoke sourdough crackers', 'en', 'smoke-snacks'],
    'smoke-search-levain' => ['Smoke levain naturel', 'fr', 'smoke-pain'],
];

switch ($args[0] ?? '') {
    case 'setup':
        foreach ([
            ['English',  'en', 'en_US', 'us'],
            ['Français', 'fr', 'fr_FR', 'fr'],
        ] as [$name, $slug, $locale, $flag]) {
            if ($model->get_language($slug)) {
                continue;
            }

            $language = [
                'name' => $name,
                'slug' => $slug,
                'locale' => $locale,
                'rtl' => false,
                'term_group' => 0,
                'flag' => $flag,
            ];
            method_exists($model, 'add_language') ? $model->add_language($language) : $model->languages->add($language);
        }

        foreach ($posts as $slug => [$title, $language, $category]) {
            $term = term_exists($category, 'category') ?: wp_insert_term(ucfirst($category), 'category', [
                'slug' => $category,
            ]);
            pll_set_term_language((int) $term['term_id'], $language);

            $id = get_page_by_path($slug, OBJECT, 'post')?->ID ?? wp_insert_post([
                'post_title' => $title,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'post',
            ]);
            pll_set_post_language($id, $language);
            wp_set_post_categories($id, [(int) $term['term_id']]);
        }

        echo json_encode(get(Indexer::class)->rebuild());
        break;

    case 'search':
        PLL()->curlang = $model->get_language($args[1] ?? 'en');
        // `s` even when empty: WordPress reads `?s=` as a search, an empty one.
        wp(['s' => $args[2] ?? ''] + array_filter(['category_name' => $args[3] ?? null]));

        $counts = [];

        foreach (get(Facets::class)->for('category') as $option) {
            if (str_starts_with($option->term->slug, 'smoke-')) {
                $counts[$option->term->slug] = $option->count;
            }
        }

        echo json_encode([
            'titles' => array_map(static fn(WP_Post $post): string => $post->post_title, $GLOBALS['wp_query']->posts),
            'counts' => $counts,
        ]);
        break;

    case 'rename':
        $post = get_page_by_path($args[1] ?? '', OBJECT, 'post');
        wp_update_post(['ID' => $post->ID, 'post_title' => $args[2] ?? '']);

        echo $post->ID;
        break;

    case 'teardown':
        foreach ($posts as $slug => [, , $category]) {
            $post = get_page_by_path($slug, OBJECT, 'post');

            if ($post !== null) {
                wp_delete_post($post->ID, true);
            }

            $term = get_term_by('slug', $category, 'category');

            if ($term) {
                wp_delete_term($term->term_id, 'category');
            }
        }

        foreach ($model->get_languages_list() as $language) {
            method_exists($model, 'delete_language')
                ? $model->delete_language($language->term_id)
                : $model->languages->delete($language->term_id);
        }

        $directory = get(SearchConfig::class)->directory();
        exec('rm -rf ' . escapeshellarg($directory));

        echo 'done';
        break;
}
