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
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'demo_contact_email' => Setting::string(sanitize: 'sanitize_email'),
            'demo_show_banner' => Setting::bool(default: false),
            'demo_posts_per_archive' => Setting::int(default: 12),
        ];
    }
}
