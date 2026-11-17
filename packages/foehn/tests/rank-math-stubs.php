<?php

declare(strict_types=1);

/**
 * Rank Math, as far as Føhn calls it, answering from globals a test sets.
 *
 * Loaded for every test, like the WordPress stubs: a class cannot be unloaded, so the
 * plugin is "installed" for the whole suite and a test turns its modules on with
 * `$GLOBALS['rank_math_stub_modules']`. `wp_stub_reset()` turns them all off.
 */

namespace RankMath {
    if (!class_exists(Helper::class)) {
        class Helper
        {
            public static function is_module_active(string $id, bool $check_registered = true): bool
            {
                return in_array($id, $GLOBALS['rank_math_stub_modules'] ?? [], true);
            }
        }
    }
}

namespace {
    if (!function_exists('rank_math_get_sitemap_url')) {
        function rank_math_get_sitemap_url(): string
        {
            return home_url('/sitemap_index.xml');
        }
    }
}
