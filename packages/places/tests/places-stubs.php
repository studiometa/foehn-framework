<?php

declare(strict_types=1);

/**
 * The WordPress functions this package calls that the framework stubs do not define.
 */

if (!function_exists('wp_is_post_autosave')) {
    function wp_is_post_autosave(mixed $post): int|false
    {
        return $GLOBALS['places_stub_autosave'] ?? false;
    }
}

if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision(mixed $post): int|false
    {
        return $GLOBALS['places_stub_revision'] ?? false;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false ? '' : trim($email);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return filter_var(trim($url), FILTER_VALIDATE_URL) === false ? '' : trim($url);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return 1;
    }
}

if (!function_exists('is_object_in_taxonomy')) {
    function is_object_in_taxonomy(string $objectType, string $taxonomy): bool
    {
        return in_array($taxonomy, $GLOBALS['places_stub_place_taxonomies'] ?? ['place_type'], true);
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url(mixed $post = null, string $size = 'post-thumbnail'): string|false
    {
        return $GLOBALS['places_stub_thumbnail'] ?? false;
    }
}

if (!function_exists('get_user_locale')) {
    function get_user_locale(mixed $user = 0): string
    {
        return $GLOBALS['places_stub_locale'] ?? 'en_US';
    }
}

if (!function_exists('get_the_excerpt')) {
    function get_the_excerpt(mixed $post = null): string
    {
        return $GLOBALS['places_stub_excerpt'] ?? '';
    }
}
