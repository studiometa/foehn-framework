<?php

declare(strict_types=1);

use Demo\Settings\IdentitySettings;
use Demo\Settings\StudioSettings;
use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Contracts\SettingsInterface;

describe('Customizer settings', function () {
    it('adds a Studio section to the Customizer', function () {
        $section = new ReflectionClass(StudioSettings::class)->getAttributes(AsCustomizerSection::class)[0]->newInstance();

        expect(is_subclass_of(StudioSettings::class, SettingsInterface::class))->toBeTrue();
        expect($section->id)->toBe('demo_studio');
        expect(array_keys(StudioSettings::settings()))->toBe([
            'demo_studio_address',
            'demo_studio_phone',
            'demo_instagram',
            'demo_show_credits',
        ]);
    });

    it('edits each studio detail with a native control', function () {
        $controls = array_map(static fn($setting): string => $setting->control, StudioSettings::settings());

        expect($controls)->toBe([
            'demo_studio_address' => 'textarea',
            'demo_studio_phone' => 'tel',
            'demo_instagram' => 'url',
            'demo_show_credits' => 'checkbox',
        ]);
    });

    it('adds its field to Site Identity, the core section', function () {
        $section = new ReflectionClass(IdentitySettings::class)->getAttributes(AsCustomizerSection::class)[0]->newInstance();

        expect($section->id)->toBe('title_tagline');
    });
});
