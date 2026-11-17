<?php

declare(strict_types=1);

namespace Tests\Fixtures\Customizer;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * Fields added to the core Site Identity section.
 */
#[AsCustomizerSection(id: 'title_tagline', title: 'Not used')]
final class SiteIdentityFixture implements SettingsInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return ['foehn_baseline' => Setting::string(label: 'Baseline')];
    }
}
