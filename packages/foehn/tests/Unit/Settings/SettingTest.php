<?php

declare(strict_types=1);

use Studiometa\Foehn\Settings\Setting;
use Tests\Fixtures\Settings\ThemeSettingsFixture;

describe('Setting', function () {
    it('describes a string', function () {
        $setting = Setting::string();

        expect($setting->type)->toBe('string');
        expect($setting->default)->toBe('');
        expect($setting->sanitize)->toBeNull();
    });

    it('keeps a setting out of REST by default', function () {
        // Unlike post meta. Settings are configuration, sometimes credentials.
        expect(Setting::string()->showInRest)->toBeFalse();
        expect(Setting::bool()->showInRest)->toBeFalse();
        expect(Setting::int()->showInRest)->toBeFalse();
        expect(Setting::number()->showInRest)->toBeFalse();
    });

    it('describes the other three types', function () {
        expect(Setting::bool(default: true)->type)->toBe('boolean');
        expect(Setting::bool(default: true)->default)->toBeTrue();
        expect(Setting::int(default: 5)->type)->toBe('integer');
        expect(Setting::int(default: 5)->default)->toBe(5);
        expect(Setting::number(default: 1.5)->type)->toBe('number');
        expect(Setting::number(default: 1.5)->default)->toBe(1.5);
    });

    it('falls back to a sanitiser its type implies', function () {
        expect(Setting::string()->sanitizer())->toBe('sanitize_text_field');
        expect(Setting::bool()->sanitizer())->toBe('rest_sanitize_boolean');
        expect(Setting::int()->sanitizer())->toBe('absint');
        expect(Setting::number()->sanitizer())->toBe('floatval');
    });

    it('takes a function name over the fallback', function () {
        expect(Setting::string(sanitize: 'sanitize_email')->sanitizer())->toBe('sanitize_email');
    });

    it('resolves a method on the page that declared it', function () {
        expect(Setting::number(sanitize: 'clampRatio')->sanitizer(ThemeSettingsFixture::class))->toBe([
            ThemeSettingsFixture::class,
            'clampRatio',
        ]);
    });

    it('leaves a function name alone when the page has no such method', function () {
        expect(Setting::string(sanitize: 'sanitize_email')->sanitizer(ThemeSettingsFixture::class))
            ->toBe('sanitize_email');
    });

    it('is readonly', function () {
        expect(Setting::class)->toBeReadonlyClass();
    });

    it('carries a description, shown under the field and in REST', function () {
        expect(Setting::string(description: 'Who to write to')->description)->toBe('Who to write to');
    });

    it('labels its field with the declared label, or the option name', function () {
        expect(Setting::string(label: 'Phone')->label('theme_phone'))->toBe('Phone');
        expect(Setting::string()->label('theme_phone'))->toBe('theme_phone');
    });

    it('picks the native control each type is edited with', function () {
        expect(Setting::string()->control)->toBe('text');
        expect(Setting::string(control: 'textarea')->control)->toBe('textarea');
        expect(Setting::bool()->control)->toBe('checkbox');
        expect(Setting::int()->control)->toBe('number');
        expect(Setting::number()->control)->toBe('number');
    });

    it('becomes a select when it offers options', function () {
        expect(Setting::string(options: ['a' => 'A'])->control)->toBe('select');
        expect(Setting::int(options: [3 => 'Three'])->control)->toBe('select');
    });

    it('refuses a control a string cannot use', function () {
        Setting::string(control: 'checkbox');
    })->throws(InvalidArgumentException::class, 'Unknown control "checkbox"');

    it('falls back to a sanitiser its control implies', function () {
        expect(Setting::string(control: 'email')->sanitizer())->toBe('sanitize_email');
        expect(Setting::string(control: 'url')->sanitizer())->toBe('esc_url_raw');
        expect(Setting::string(control: 'textarea')->sanitizer())->toBe('sanitize_textarea_field');
        expect(Setting::string(control: 'tel')->sanitizer())->toBe('sanitize_text_field');
    });

    it('keeps a select to one of its options, or the default', function () {
        $sanitize = Setting::int(default: 3, options: [3 => '3', 6 => '6'])->sanitizer();

        expect($sanitize('6'))->toBe(6);
        expect($sanitize('7'))->toBe(3);
        expect($sanitize(['6']))->toBe(3);
    });

    it('names its sanitiser for inspect', function () {
        expect(Setting::string()->sanitizerName())->toBe('sanitize_text_field');
        expect(Setting::string(options: ['a' => 'A'])->sanitizerName())->toBe('one of the options');
        expect(Setting::number(sanitize: 'clampRatio')->sanitizerName(ThemeSettingsFixture::class))
            ->toBe(ThemeSettingsFixture::class . '::clampRatio');
    });
});
