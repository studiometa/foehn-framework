<?php

declare(strict_types=1);

namespace Demo\Settings;

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

/**
 * A field added to Site Identity, the core section of the site title and tagline.
 *
 * `title_tagline` is the id of that section, which exists already: Føhn adds the
 * field to it rather than a second section, and the title of the attribute is
 * not used.
 */
#[AsCustomizerSection(id: 'title_tagline', title: 'Site Identity')]
final readonly class IdentitySettings implements SettingsInterface
{
    /**
     * @return array<string, Setting>
     */
    public static function settings(): array
    {
        return [
            'demo_signature' => Setting::string(
                default: 'Photographs made in Strasbourg',
                label: 'Footer signature',
                description: 'The line at the bottom of every page.',
            ),
        ];
    }
}
