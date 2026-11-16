<?php

/**
 * Rank Math stubs for static analysis.
 *
 * Only what Føhn calls. Rank Math is a plugin of the site, not a dependency of the
 * framework: these declarations are never loaded at runtime.
 *
 * @see https://wordpress.org/plugins/seo-by-rank-math/
 */

namespace RankMath {
    class Helper
    {
        /**
         * @param string $id Module ID, such as `sitemap` or `redirections`
         * @param bool $check_registered Whether the module must also be registered
         */
        public static function is_module_active(string $id, bool $check_registered = true): bool {}
    }
}

namespace {
    /**
     * The URL of the sitemap index.
     */
    function rank_math_get_sitemap_url(): string {}
}
