<?php

declare(strict_types=1);

namespace Tests\Fixtures\Customizer;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsCustomizerSection(id: 'foehn_contact', title: 'Contact', description: 'Shown in the footer', priority: 30)]
final class ContactSectionFixture implements SettingsInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'foehn_phone' => Setting::string(label: 'Phone', control: 'tel'),
            'foehn_website' => Setting::string(label: 'Website', control: 'url'),
            'foehn_show_map' => Setting::bool(label: 'Map', description: 'Show the map'),
            'foehn_layout' => Setting::string(default: 'grid', options: ['grid' => 'Grid', 'list' => 'List']),
        ];
    }
}
