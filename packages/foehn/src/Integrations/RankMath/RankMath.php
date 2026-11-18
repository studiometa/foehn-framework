<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Integrations\RankMath;

use RankMath\Frontend\Breadcrumbs;
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
     * Whether Rank Math prints a JSON-LD graph: its Schema module (`rich-snippet`) is on.
     *
     * Rank Math builds that graph through the `rank_math/json_ld` filter.
     */
    public static function printsJsonLd(): bool
    {
        return self::isModuleActive('rich-snippet');
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

    /**
     * The breadcrumb trail of the current request, as Rank Math builds it.
     *
     * Plain-text labels, so the theme escapes them as any other text. The items follow
     * Rank Math's own markup: the current page has no URL, and the "remove post title"
     * setting drops it from the trail of a post or a page. Empty when Rank Math or its
     * breadcrumbs are off.
     *
     * @return list<array{label: string, url: string|null}>
     */
    public static function breadcrumbs(): array
    {
        $breadcrumbs = self::isActive() ? Breadcrumbs::get() : false;

        if ($breadcrumbs === false) {
            return [];
        }

        $crumbs = array_values($breadcrumbs->get_crumbs());
        $titleRemoved =
            (is_single() || is_page()) && (bool) Helper::get_settings('general.breadcrumbs_remove_post_title');

        if ($titleRemoved) {
            array_pop($crumbs);
        }

        $last = count($crumbs) - 1;
        $items = [];

        foreach ($crumbs as $index => $crumb) {
            $url = (string) ($crumb[1] ?? '');

            $items[] = [
                'label' => html_entity_decode((string) ($crumb[0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url' => $url === '' || $index === $last && !$titleRemoved ? null : $url,
            ];
        }

        return $items;
    }
}
