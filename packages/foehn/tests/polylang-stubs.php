<?php

declare(strict_types=1);

/**
 * Polylang, as far as Føhn calls it, answering from globals a test sets.
 *
 * Loaded for every test, like the Rank Math stubs: the functions cannot be undefined, so
 * Polylang is "installed" for the whole suite, and it has no language until a test sets
 * `$GLOBALS['pll_stub_languages']` (code => name). The first language is the default.
 * `pll_stub_current` is the current language (null: none, "all languages" in the
 * admin), `pll_stub_post_translations` and `pll_stub_term_translations` map an id to
 * its translations (code => id), the object itself included, which also gives its
 * language. Every post type is translated, except those in
 * `pll_stub_untranslated_post_types`. `wp_stub_reset()` empties them all.
 */

if (!function_exists('pll_languages_list')) {
    /**
     * @param array{fields?: string} $args
     * @return list<string>
     */
    function pll_languages_list(array $args = []): array
    {
        $languages = $GLOBALS['pll_stub_languages'] ?? [];

        return ($args['fields'] ?? 'slug') === 'name' ? array_values($languages) : array_keys($languages);
    }
}

if (!function_exists('pll_default_language')) {
    function pll_default_language(string $field = 'slug'): string|false
    {
        $codes = array_keys($GLOBALS['pll_stub_languages'] ?? []);

        return $codes[0] ?? false;
    }
}

if (!function_exists('pll_current_language')) {
    function pll_current_language(string $field = 'slug'): string|false
    {
        return $GLOBALS['pll_stub_current'] ?? false;
    }
}

if (!function_exists('pll_is_translated_post_type')) {
    function pll_is_translated_post_type(string $post_type): bool
    {
        return !in_array($post_type, $GLOBALS['pll_stub_untranslated_post_types'] ?? [], true);
    }
}

if (!function_exists('pll_get_post_language')) {
    function pll_get_post_language(int $post_id, string $field = 'slug'): string|false
    {
        $language = array_search($post_id, $GLOBALS['pll_stub_post_translations'][$post_id] ?? [], true);

        return is_string($language) ? $language : false;
    }
}

if (!function_exists('pll_get_term_language')) {
    function pll_get_term_language(int $term_id, string $field = 'slug'): string|false
    {
        $language = array_search($term_id, $GLOBALS['pll_stub_term_translations'][$term_id] ?? [], true);

        return is_string($language) ? $language : false;
    }
}

if (!function_exists('pll_get_post_translations')) {
    /**
     * @return array<string, int>
     */
    function pll_get_post_translations(int $post_id): array
    {
        return $GLOBALS['pll_stub_post_translations'][$post_id] ?? [];
    }
}

if (!function_exists('pll_get_term_translations')) {
    /**
     * @return array<string, int>
     */
    function pll_get_term_translations(int $term_id): array
    {
        return $GLOBALS['pll_stub_term_translations'][$term_id] ?? [];
    }
}

if (!function_exists('pll_home_url')) {
    function pll_home_url(string $lang = ''): string
    {
        return home_url($lang === pll_default_language() ? '/' : '/' . $lang . '/');
    }
}
