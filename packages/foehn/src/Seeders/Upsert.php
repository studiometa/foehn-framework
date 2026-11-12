<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Seeders;

use RuntimeException;

/**
 * The idempotent writes a seeder makes most often.
 */
final class Upsert
{
    /**
     * Create a post, or update the post of this type that has this slug.
     *
     * The post is found by type and slug in any status, so a second run updates
     * the post a first run wrote and never creates a copy. The status is
     * `publish` unless `$data` sets one.
     *
     * @param string $type Post type
     * @param string $slug Post name, the key a second run finds the post by
     * @param array<string, mixed> $data Fields for wp_insert_post(), e.g. post_title, post_content
     * @param array<string, mixed> $meta Meta key => value, written with update_post_meta()
     * @return int The post ID
     * @throws RuntimeException When WordPress refuses the post
     */
    public static function post(string $type, string $slug, array $data = [], array $meta = []): int
    {
        $existing = get_posts([
            'post_type' => $type,
            'post_status' => 'any',
            'name' => $slug,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        $payload = array_merge(['post_type' => $type, 'post_name' => $slug, 'post_status' => 'publish'], $data);

        if ($existing !== []) {
            $payload['ID'] = (int) $existing[0];
        }

        $id = wp_insert_post($payload, true);

        if (is_wp_error($id)) {
            throw new RuntimeException(sprintf('Could not write %s/%s: %s', $type, $slug, $id->get_error_message()));
        }

        foreach ($meta as $key => $value) {
            update_post_meta($id, $key, $value);
        }

        return $id;
    }
}
