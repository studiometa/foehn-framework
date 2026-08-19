<?php

declare(strict_types=1);

use App\Settings\ThemeSettings;
use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsPageInterface;
use Studiometa\Foehn\Settings\Setting;

describe('ThemeSettings', function () {
    it('implements SettingsPageInterface', function () {
        expect(is_subclass_of(ThemeSettings::class, SettingsPageInterface::class))->toBeTrue();
    });

    it('has AsSettingsPage attribute with correct config', function () {
        $attrs = new ReflectionClass(ThemeSettings::class)->getAttributes(AsSettingsPage::class);

        expect($attrs)->toHaveCount(1);

        $attr = $attrs[0]->newInstance();

        expect($attr->slug)->toBe('theme-settings');
        expect($attr->title)->toBe('Theme settings');
        expect($attr->parent)->toBe('themes.php');
        expect($attr->capability)->toBe('manage_options');
    });

    it('declares what it stores', function () {
        $settings = ThemeSettings::settings();

        expect(array_keys($settings))->toBe([
            'starter_contact_email',
            'starter_show_banner',
            'starter_posts_per_archive',
        ]);
        expect($settings['starter_contact_email'])->toBeInstanceOf(Setting::class);
    });

    it('names the option keys after the theme', function () {
        // The Settings API has no namespacing: each key becomes a WordPress
        // option of that exact name, on a site that may run other plugins.
        foreach (array_keys(ThemeSettings::settings()) as $name) {
            expect($name)->toStartWith('starter_');
        }
    });

    it('types each setting, and sanitizes the email as one', function () {
        $settings = ThemeSettings::settings();

        expect($settings['starter_show_banner']->type)->toBe('boolean');
        expect($settings['starter_posts_per_archive']->type)->toBe('integer');
        expect($settings['starter_posts_per_archive']->default)->toBe(12);
        expect($settings['starter_contact_email']->sanitizer())->toBe('sanitize_email');
    });

    it('keeps every setting out of REST', function () {
        foreach (ThemeSettings::settings() as $setting) {
            expect($setting->showInRest)->toBeFalse();
        }
    });
});
