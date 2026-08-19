<?php

declare(strict_types=1);

namespace Tests\Fixtures\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(slug: 'theme-settings', title: 'Theme settings', parent: 'themes.php')]
final class ThemeSettingsFixture implements SettingsPageInterface
{
    public static int $rendered = 0;

    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'foehn_contact_email' => Setting::string(sanitize: 'sanitize_email'),
            'foehn_show_banner' => Setting::bool(default: false),
            'foehn_posts_per_page' => Setting::int(default: 10, showInRest: true, description: 'How many'),
            'foehn_ratio' => Setting::number(default: 1.5, sanitize: 'clampRatio'),
        ];
    }

    public static function clampRatio(mixed $value): float
    {
        return min(2.0, max(0.5, (float) $value));
    }

    public function render(): void
    {
        self::$rendered++;

        echo '<p class="fields">the form fields</p>';
    }
}
