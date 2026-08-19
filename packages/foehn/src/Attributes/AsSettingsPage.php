<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register an admin page backed by the WordPress Settings API.
 *
 * Named for the API it wraps. WordPress uses both words — `add_options_page()`
 * registers the menu entry, while everything that does the work is
 * `register_setting()`, `add_settings_section()` and `settings_fields()` — and
 * the plural follows those, leaving #[AsAcfOptionsPage] unambiguously the ACF
 * one.
 *
 * Foehn provides the menu entry, the registration of each declared setting, and
 * the page shell: `settings_errors()`, the form, `settings_fields()`,
 * `do_settings_sections()` and the submit button. The body of the form is the
 * page's own, and comes from one of two places — a Twig template named here, or
 * a `form()` method through SettingsFormInterface.
 *
 * Usage:
 * ```php
 * #[AsSettingsPage(
 *     slug: 'theme-settings',
 *     title: 'Theme settings',
 *     parent: 'themes.php',
 *     template: 'settings/theme-settings',
 * )]
 * final readonly class ThemeSettings implements SettingsPageInterface
 * {
 *     public static function settings(): array
 *     {
 *         return [
 *             'contact_email' => Setting::string(sanitize: 'sanitize_email'),
 *             'show_banner' => Setting::bool(default: false),
 *         ];
 *     }
 * }
 * ```
 *
 * @see \Studiometa\Foehn\Contracts\SettingsPageInterface
 * @see \Studiometa\Foehn\Contracts\SettingsFormInterface
 * @see \Studiometa\Foehn\Settings\Setting
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsSettingsPage
{
    /**
     * @param string $slug The page's slug, which is also the option group its
     *   settings are registered under
     * @param string $title Shown as the page heading, and in the menu unless
     *   $menuTitle says otherwise
     * @param string|null $menuTitle The menu entry's label
     * @param string|null $parent The admin menu the page sits under, e.g.
     *   `options-general.php` or `themes.php`. `null` makes it a top-level menu
     * @param string $capability What a user needs to reach the page at all
     * @param string|null $icon A dashicon, URL or base64 SVG. Top-level pages only
     * @param int|null $position Where in the menu. Top-level pages only
     * @param string|null $template A Twig template rendered as the body of the
     *   form, with the page's current values in `settings`. A page that names one
     *   needs no PHP of its own; a page that needs more than the values can
     *   implement SettingsFormInterface instead
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $menuTitle = null,
        public ?string $parent = 'options-general.php',
        public string $capability = 'manage_options',
        public ?string $icon = null,
        public ?int $position = null,
        public ?string $template = null,
    ) {}

    /**
     * The label the menu entry carries.
     */
    public function menuTitle(): string
    {
        return $this->menuTitle ?? $this->title;
    }
}
