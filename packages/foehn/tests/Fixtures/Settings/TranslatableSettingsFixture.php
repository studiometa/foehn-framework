<?php

declare(strict_types=1);

namespace Tests\Fixtures\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

#[AsSettingsPage(slug: 'site-texts', title: 'Site texts')]
final class TranslatableSettingsFixture implements SettingsInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'foehn_tagline' => Setting::string(default: 'Hello', label: 'Tagline', translatable: true),
            'foehn_phone' => Setting::string(label: 'Phone', control: 'tel'),
        ];
    }
}
