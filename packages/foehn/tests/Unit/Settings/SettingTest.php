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
        expect(Setting::class)->toBeReadonly();
    });

    it('carries a description, which only shows through REST', function () {
        expect(Setting::string(showInRest: true, description: 'Who to write to')->description)->toBe('Who to write to');
    });
});
