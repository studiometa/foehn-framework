<?php

declare(strict_types=1);

namespace Demo\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * A settings screen under Appearance, on the WordPress Settings API.
 *
 * `settings()` says what is stored and which native field edits it. Føhn draws
 * the whole page from that: the heading, the form, the nonce, a WordPress field
 * for each setting and the submit button. So this class has no PHP of its own
 * beyond the declaration, and the page looks like the WordPress screens around it.
 */
#[AsSettingsPage(
    slug: 'theme-settings',
    title: 'Theme settings',
    parent: 'themes.php',
)]
final readonly class ThemeSettings implements SettingsInterface
{
    /**
     * The page sizes the projects archive offers, the first one being the default.
     *
     * The same three values as `query-filters.config.php` and `page-cache.config.php`:
     * a default the visitor's own select cannot choose would be a page the cache
     * cannot store.
     */
    public const array PAGE_SIZES = [3, 6, 12];

    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'demo_contact_email' => Setting::string(label: 'Contact email', control: 'email'),
            'demo_show_banner' => Setting::bool(label: 'Banner', description: 'Show the announcement banner'),
            'demo_banner_text' => Setting::string(label: 'Banner text'),
            'demo_posts_per_archive' => Setting::int(
                default: self::PAGE_SIZES[0],
                label: 'Projects per page',
                options: array_combine(self::PAGE_SIZES, array_map(strval(...), self::PAGE_SIZES)),
                sanitize: 'sanitizePostsPerArchive',
            ),
        ];
    }

    /**
     * Keep the page size to one of PAGE_SIZES, whatever the form posted.
     */
    public static function sanitizePostsPerArchive(mixed $value): int
    {
        $size = is_numeric($value) ? (int) $value : 0;

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::PAGE_SIZES[0];
    }
}
