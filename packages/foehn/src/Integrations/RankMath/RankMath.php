<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Integrations\RankMath;

use RankMath\Helper;

/**
 * What Føhn asks Rank Math, in one place.
 *
 * Rank Math is a plugin of the site, never a dependency of the framework: every answer
 * here is "no" when the plugin is not loaded. The rest of the framework asks this class
 * and does not call Rank Math itself.
 *
 * @see https://wordpress.org/plugins/seo-by-rank-math/
 */
final class RankMath
{
    /**
     * Whether Rank Math is loaded.
     */
    public static function isActive(): bool
    {
        return class_exists(Helper::class);
    }

    /**
     * Whether a Rank Math module, such as `sitemap` or `redirections`, is on.
     *
     * Read from the list of modules the site turned on, and not from the modules Rank
     * Math has registered: it registers them on `after_setup_theme`, after the theme
     * boots Føhn.
     */
    public static function isModuleActive(string $module): bool
    {
        return self::isActive() && Helper::is_module_active($module, false);
    }

    /**
     * The URL of the sitemap index of Rank Math, or null when its sitemap module is off.
     */
    public static function sitemapUrl(): ?string
    {
        if (!self::isModuleActive('sitemap') || !function_exists('rank_math_get_sitemap_url')) {
            return null;
        }

        return rank_math_get_sitemap_url();
    }
}
