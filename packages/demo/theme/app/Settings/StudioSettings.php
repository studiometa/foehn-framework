<?php

declare(strict_types=1);

namespace Demo\Settings;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * The studio details in the footer, in Appearance → Customize, next to the live
 * preview.
 *
 * The same declaration as ThemeSettings, in another place: these values change
 * how every page looks, so the editor sees the change before saving it. They are
 * stored as options, and Settings::get() reads them as it reads ThemeSettings.
 */
#[AsCustomizerSection(id: 'demo_studio', title: 'Studio', priority: 30)]
final readonly class StudioSettings implements SettingsInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'demo_studio_address' => Setting::string(label: 'Address', control: 'textarea'),
            'demo_studio_phone' => Setting::string(label: 'Phone', control: 'tel'),
            'demo_instagram' => Setting::string(label: 'Instagram', control: 'url'),
            'demo_show_credits' => Setting::bool(
                default: true,
                label: 'Credits',
                description: 'Show the photograph credits in the footer',
            ),
        ];
    }
}
