<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Search;

use WP_Post;
use WP_Term;

/**
 * The default fields of a search document.
 */
final class Document
{
    /**
     * The title, the excerpt and the content as plain text, the names of the terms of
     * every taxonomy of the post, and the publication date as a timestamp.
     *
     * @return array{title: string, excerpt: string, content: string, terms: list<string>, date: int}
     */
    public static function fromPost(WP_Post $post): array
    {
        return [
            'title' => self::plainText($post->post_title),
            'excerpt' => self::plainText($post->post_excerpt),
            'content' => self::plainText(strip_shortcodes($post->post_content)),
            'terms' => self::terms($post),
            'date' => (int) strtotime($post->post_date_gmt !== '' ? $post->post_date_gmt . ' UTC' : $post->post_date),
        ];
    }

    /**
     * Text without tags, block comments, entities and repeated white space.
     */
    public static function plainText(string $html): string
    {
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @return list<string>
     */
    private static function terms(WP_Post $post): array
    {
        $names = [];

        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            // A WP_Error, for a taxonomy that is gone, holds no WP_Term.
            foreach (wp_get_post_terms($post->ID, $taxonomy) as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                $names[] = $term->name;
            }
        }

        return array_values(array_unique($names));
    }
}
