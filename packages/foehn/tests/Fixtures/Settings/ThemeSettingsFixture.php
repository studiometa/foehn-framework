<?php

declare(strict_types=1);

namespace Tests\Fixtures\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(slug: 'theme-settings', title: 'Theme settings', parent: 'themes.php')]
final class ThemeSettingsFixture implements SettingsPageInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'foehn_contact_email' => Setting::string(label: 'Contact email', control: 'email'),
            'foehn_show_banner' => Setting::bool(label: 'Banner', description: 'Show the banner'),
            'foehn_posts_per_page' => Setting::int(default: 10, description: 'How many', showInRest: true),
            'foehn_ratio' => Setting::number(default: 1.5, label: 'Ratio', sanitize: 'clampRatio'),
            'foehn_layout' => Setting::string(
                default: 'grid',
                label: 'Layout',
                options: ['grid' => 'Grid', 'list' => 'List'],
            ),
        ];
    }

    public static function clampRatio(mixed $value): float
    {
        return min(2.0, max(0.5, (float) $value));
    }
}
