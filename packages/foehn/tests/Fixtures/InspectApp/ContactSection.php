<?php

declare(strict_types=1);

namespace Tests\Fixtures\InspectApp;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * A Customizer section.
 */
#[AsCustomizerSection(id: 'theme_contact', title: 'Contact')]
final class ContactSection implements SettingsInterface
{
    public static function settings(): array
    {
        return ['theme_phone' => Setting::string(label: 'Phone', control: 'tel')];
    }
}
