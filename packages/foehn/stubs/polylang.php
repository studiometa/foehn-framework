<?php

/**
 * Polylang stubs for static analysis.
 *
 * Only what Føhn calls, from the public API of free Polylang. Polylang is a plugin of
 * the site, not a dependency of the framework: these declarations are never loaded at
 * runtime.
 *
 * @see https://polylang.pro/doc/function-reference/
 */

/**
 * @param array{fields?: string, hide_empty?: bool} $args
 * @return list<string>
 */
function pll_languages_list(array $args = []): array {}

function pll_default_language(string $field = 'slug'): string|false {}

function pll_current_language(string $field = 'slug'): string|false {}

function pll_get_post_language(int $post_id, string $field = 'slug'): string|false {}

function pll_get_term_language(int $term_id, string $field = 'slug'): string|false {}

/**
 * @return array<string, int>
 */
function pll_get_post_translations(int $post_id): array {}

/**
 * @return array<string, int>
 */
function pll_get_term_translations(int $term_id): array {}

function pll_home_url(string $lang = ''): string {}
