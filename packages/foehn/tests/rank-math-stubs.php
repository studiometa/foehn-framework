<?php

declare(strict_types=1);

/**
 * Rank Math, as far as Føhn calls it, answering from globals a test sets.
 *
 * Loaded for every test, like the WordPress stubs: a class cannot be unloaded, so the
 * plugin is "installed" for the whole suite and a test turns its modules on with
 * `$GLOBALS['rank_math_stub_modules']`, its settings with `rank_math_stub_settings` and
 * its breadcrumbs with `rank_math_stub_crumbs` (null: breadcrumbs off).
 * `wp_stub_reset()` turns them all off.
 */

namespace RankMath {
    if (!class_exists(Helper::class)) {
        class Helper
        {
            public static function is_module_active(string $id, bool $check_registered = true): bool
            {
                return in_array($id, $GLOBALS['rank_math_stub_modules'] ?? [], true);
            }

            public static function get_settings(string $field_id = '', mixed $default_value = false): mixed
            {
                return $GLOBALS['rank_math_stub_settings'][$field_id] ?? $default_value;
            }
        }
    }
}

namespace RankMath {
    if (!class_exists(Settings::class)) {
        /**
         * The loaded settings, kept in `$GLOBALS['rank_math_stub_loaded']` once read.
         */
        class Settings
        {
            public function get(string $field_id = '', mixed $default_value = false): mixed
            {
                $GLOBALS['rank_math_stub_loaded'] ??= $GLOBALS['rank_math_stub_settings'] ?? [];

                return $GLOBALS['rank_math_stub_loaded'][$field_id] ?? $default_value;
            }

            public function set(string $group, string $id, mixed $value): void
            {
                $GLOBALS['rank_math_stub_loaded'][$group . '.' . $id] = $value;
            }
        }
    }
}

namespace RankMath\Frontend {
    if (!class_exists(Breadcrumbs::class)) {
        class Breadcrumbs
        {
            public static function get(): self|false
            {
                return ($GLOBALS['rank_math_stub_crumbs'] ?? null) === null ? false : new self();
            }

            public function get_crumbs(): array
            {
                return $GLOBALS['rank_math_stub_crumbs'];
            }
        }
    }
}

namespace {
    if (!function_exists('rank_math')) {
        function rank_math(): object
        {
            return new class {
                public \RankMath\Settings $settings;

                public function __construct()
                {
                    $this->settings = new \RankMath\Settings();
                }
            };
        }
    }

    if (!function_exists('rank_math_get_sitemap_url')) {
        function rank_math_get_sitemap_url(): string
        {
            return home_url('/sitemap_index.xml');
        }
    }
}
