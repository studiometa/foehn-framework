<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a section of the Customizer (Appearance → Customize).
 *
 * The same declaration as #[AsSettingsPage], in another place: the class
 * implements SettingsInterface, and Føhn adds a native Customizer control for
 * each setting, next to the live preview of the site. The settings are stored
 * as options, so `Settings::get()` reads them as it reads a settings page.
 *
 * Usage:
 * ```php
 * #[AsCustomizerSection(id: 'theme_contact', title: 'Contact')]
 * final readonly class ContactSettings implements SettingsInterface
 * {
 *     public static function settings(): array
 *     {
 *         return [
 *             'theme_phone' => Setting::string(label: 'Phone', control: 'tel'),
 *             'theme_address' => Setting::string(label: 'Address', control: 'textarea'),
 *         ];
 *     }
 * }
 * ```
 *
 * @see \Studiometa\Foehn\Contracts\SettingsInterface
 * @see \Studiometa\Foehn\Settings\Setting
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsCustomizerSection
{
    /**
     * @param string $id The section id, which is also the option group its
     *   settings are registered under. The id of a section that exists, from
     *   core or a plugin, adds the fields to that section, and the other
     *   arguments are not used: `title_tagline` is Site Identity, `colors`,
     *   `header_image`, `background_image` and `static_front_page` are the others
     * @param string $title The section heading
     * @param string $description Shown at the top of the section
     * @param string|null $panel The id of the panel the section sits in
     * @param int $priority Where in the Customizer. Core sections run from 20
     *   (Site Identity) to 160
     * @param string $capability What a user needs to see the section and save
     *   its settings
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description = '',
        public ?string $panel = null,
        public int $priority = 160,
        public string $capability = 'edit_theme_options',
    ) {}
}
