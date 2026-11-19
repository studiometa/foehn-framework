<?php

declare(strict_types=1);

use Demo\Settings\ThemeSettings;
use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Contracts\SettingsInterface;
use Studiometa\Foehn\Settings\Setting;

describe('ThemeSettings', function () {
    it('implements SettingsInterface', function () {
        expect(is_subclass_of(ThemeSettings::class, SettingsInterface::class))->toBeTrue();
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

    it('edits each setting with a native field', function () {
        $settings = ThemeSettings::settings();

        expect($settings['demo_contact_email']->control)->toBe('email');
        expect($settings['demo_show_banner']->control)->toBe('checkbox');
        expect($settings['demo_posts_per_archive']->control)->toBe('select');
        expect($settings['demo_posts_per_archive']->options)->toBe([3 => '3', 6 => '6', 12 => '12']);
    });

    it('declares what it stores', function () {
        $settings = ThemeSettings::settings();

        expect(array_keys($settings))->toBe([
            'demo_contact_email',
            'demo_show_banner',
            'demo_banner_text',
            'demo_posts_per_archive',
        ]);
        expect($settings['demo_contact_email'])->toBeInstanceOf(Setting::class);
    });

    it('names the option keys after the theme', function () {
        // The Settings API has no namespacing: each key becomes a WordPress
        // option of that exact name, on a site that may run other plugins.
        foreach (array_keys(ThemeSettings::settings()) as $name) {
            expect($name)->toStartWith('demo_');
        }
    });

    it('types each setting, and sanitizes the email as one', function () {
        $settings = ThemeSettings::settings();

        expect($settings['demo_show_banner']->type)->toBe('boolean');
        expect($settings['demo_posts_per_archive']->type)->toBe('integer');
        expect($settings['demo_posts_per_archive']->default)->toBe(3);
        expect($settings['demo_contact_email']->sanitizer())->toBe('sanitize_email');
    });

    it('keeps the page size to the sizes the archive offers', function () {
        expect(ThemeSettings::sanitizePostsPerArchive('6'))->toBe(6);
        expect(ThemeSettings::sanitizePostsPerArchive(12))->toBe(12);
        expect(ThemeSettings::sanitizePostsPerArchive('999'))->toBe(3);
        expect(ThemeSettings::sanitizePostsPerArchive(''))->toBe(3);
        expect(ThemeSettings::settings()['demo_posts_per_archive']->sanitizer(ThemeSettings::class))->toBe([
            ThemeSettings::class,
            'sanitizePostsPerArchive',
        ]);
    });

    it('translates the banner text, and nothing else', function () {
        $translatable = array_keys(array_filter(
            ThemeSettings::settings(),
            static fn(Setting $setting): bool => $setting->translatable,
        ));

        expect($translatable)->toBe(['demo_banner_text']);
    });

    it('keeps every setting out of REST', function () {
        foreach (ThemeSettings::settings() as $setting) {
            expect($setting->showInRest)->toBeFalse();
        }
    });
});
