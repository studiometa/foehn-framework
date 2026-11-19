<?php

declare(strict_types=1);

use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\SettingField;

describe('SettingField', function () {
    it('draws a text control as a regular-text input of its type', function (string $control) {
        expect(SettingField::render('theme_x', Setting::string(control: $control), 'a'))
            ->toBe(sprintf('<input type="%s" id="theme_x" name="theme_x" value="a" class="regular-text" />', $control));
    })->with(['text', 'email', 'url', 'tel']);

    it('draws a textarea as large-text', function () {
        expect(SettingField::render('theme_address', Setting::string(control: 'textarea'), "1 rue\nParis"))
            ->toBe(
                '<textarea id="theme_address" name="theme_address" rows="5" class="large-text">1 rue'
                . "\n"
                . 'Paris</textarea>',
            );
    });

    it('draws a number as small-text, stepping by one for an integer', function () {
        expect(SettingField::render('theme_n', Setting::int(), 4))->toContain('value="4" step="1" class="small-text"');
        expect(SettingField::render('theme_r', Setting::number(), 1.5))->toContain('value="1.5" step="any"');
    });

    it('draws a checkbox in a fieldset, with its description as its text', function () {
        $html = SettingField::render('theme_banner', Setting::bool(label: 'Banner', description: 'Show it'), true);

        expect($html)->toBe(
            '<fieldset><legend class="screen-reader-text"><span>Banner</span></legend>'
            . '<label for="theme_banner"><input type="checkbox" id="theme_banner" name="theme_banner" value="1" checked="checked" /> Show it</label></fieldset>',
        );
    });

    it('draws a select with the current option selected', function () {
        $html = SettingField::render('theme_size', Setting::int(options: [3 => 'Three', 6 => 'Six']), 6);

        expect($html)->toBe(
            '<select id="theme_size" name="theme_size"><option value="3">Three</option>'
            . '<option value="6" selected="selected">Six</option></select>',
        );
    });

    it('prints the description under the field and points the field at it', function () {
        $html = SettingField::render('theme_gtm', Setting::string(description: 'GTM-XXXX'), '');

        expect($html)
            ->toContain('aria-describedby="theme_gtm-description"')
            ->toContain('<p class="description" id="theme_gtm-description">GTM-XXXX</p>');
    });

    it('escapes the value', function () {
        expect(SettingField::render('theme_x', Setting::string(), '"><script>'))->not->toContain('<script>');
    });

    it('draws a read-only field with a note that the configuration sets it', function () {
        $html = SettingField::render(
            'theme_token',
            Setting::string(description: 'Public token.'),
            'pk.1',
            readonly: true,
        );

        expect($html)
            ->toContain('value="pk.1"')
            ->toContain('readonly="readonly"')
            ->toContain('Public token. ' . SettingField::READONLY_NOTE);
    });

    it('disables a read-only checkbox and select, which have no read-only state', function () {
        expect(SettingField::render('theme_flag', Setting::bool(label: 'Flag'), true, readonly: true))
            ->toContain('disabled="disabled"')
            ->toContain(SettingField::READONLY_NOTE);
        expect(SettingField::render('theme_layout', Setting::string(options: ['a' => 'A']), 'a', readonly: true))
            ->toContain('disabled="disabled"');
    });
});
