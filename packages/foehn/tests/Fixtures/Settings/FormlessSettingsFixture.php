<?php

declare(strict_types=1);

namespace Tests\Fixtures\Settings;

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * A page that says what it stores and never says what its form looks like.
 */
#[AsSettingsPage(slug: 'formless-settings', title: 'Formless')]
final class FormlessSettingsFixture implements SettingsPageInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return ['foehn_nothing' => Setting::string()];
    }
}
