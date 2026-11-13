<?php

declare(strict_types=1);

namespace Demo\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * A settings screen under Appearance, on the WordPress Settings API.
 *
 * `settings()` says what is stored; the template says what the form looks like.
 * That separation is the whole difference from an ACF options page, which
 * declares both — and the reason there is no field builder here. Text inputs
 * and checkboxes are a day's work; repeaters, conditional logic and media
 * pickers are ACF's actual product.
 *
 * The form is Twig, like every other view in a Føhn theme, and this class has
 * no PHP of its own beyond the declaration. Everything around the fields — the
 * heading, the form, the nonce, the submit button — comes from the framework,
 * so a page cannot forget the one piece whose absence makes saving fail
 * silently.
 */
#[AsSettingsPage(
    slug: 'theme-settings',
    title: 'Theme settings',
    parent: 'themes.php',
    template: 'settings/theme-settings',
)]
final readonly class ThemeSettings implements SettingsPageInterface
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
            'demo_contact_email' => Setting::string(sanitize: 'sanitize_email'),
            'demo_show_banner' => Setting::bool(default: false),
            'demo_banner_text' => Setting::string(sanitize: 'sanitize_text_field'),
            'demo_posts_per_archive' => Setting::int(default: self::PAGE_SIZES[0], sanitize: 'sanitizePostsPerArchive'),
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
