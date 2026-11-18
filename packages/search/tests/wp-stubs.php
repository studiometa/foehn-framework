<?php

declare(strict_types=1);

/**
 * The WordPress functions only this package calls, beside the framework's stubs.
 */

if (!function_exists('strip_shortcodes')) {
    function strip_shortcodes(string $content): string
    {
        return (string) preg_replace('/\[[^\]]*\]/', '', $content);
    }
}

if (!function_exists('get_objects_in_term')) {
    /**
     * Answers with `$GLOBALS['wp_stub_objects_in_term'][$termId]`.
     */
    function get_objects_in_term(int|array $termIds, string|array $taxonomies, array $args = []): array
    {
        return $GLOBALS['wp_stub_objects_in_term'][(int) (is_array($termIds) ? $termIds[0] : $termIds)] ?? [];
    }
}

if (!function_exists('get_post_type_object')) {
    function get_post_type_object(string $postType): ?object
    {
        $name = $GLOBALS['wp_stub_post_type_labels'][$postType] ?? null;

        return $name === null ? null : (object) ['name' => $postType, 'labels' => (object) ['name' => $name]];
    }
}
