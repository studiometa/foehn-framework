<?php

declare(strict_types=1);

use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\Settings;

beforeEach(function () {
    wp_stub_reset();
    Settings::clear();
    $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français'];
    $GLOBALS['wp_stub_options'] = ['tagline' => 'Hello', 'tagline_fr' => 'Bonjour', 'phone' => '0102'];

    Settings::register(
        'texts',
        [
            'tagline' => Setting::string(translatable: true),
            'phone' => Setting::string(),
        ],
        stdClass::class,
    );
});

afterEach(function () {
    unset($GLOBALS['wp_stub_is_admin']);
});

describe('Settings, translatable', function () {
    it('is not translatable unless it asks', function () {
        expect(Setting::string()->translatable)->toBeFalse();
        expect(Setting::string(translatable: true)->translatable)->toBeTrue();
    });

    it('stores the default language in {name} and the others in {name}_{lang}', function () {
        expect(Settings::option('tagline', null))->toBe('tagline');
        expect(Settings::option('tagline', 'fr'))->toBe('tagline_fr');
        expect(Settings::group('texts', 'fr'))->toBe('texts_fr');
    });

    it('reads the current language on the front end', function () {
        $GLOBALS['pll_stub_current'] = 'fr';

        expect(Settings::get('tagline'))->toBe('Bonjour');
        expect(Settings::get('phone'))->toBe('0102');
    });

    it('reads the default language in the default language', function () {
        $GLOBALS['pll_stub_current'] = 'en';

        expect(Settings::get('tagline'))->toBe('Hello');
    });

    it('falls back to the default language when the translation is empty', function () {
        $GLOBALS['pll_stub_current'] = 'fr';
        $GLOBALS['wp_stub_options']['tagline_fr'] = '';

        expect(Settings::get('tagline'))->toBe('Hello');
    });

    it('reads the language of the admin bar in the admin, and the default one for all languages', function () {
        $GLOBALS['wp_stub_is_admin'] = true;
        $GLOBALS['pll_stub_current'] = 'fr';

        expect(Settings::language())->toBe('fr');
        expect(Settings::get('tagline'))->toBe('Bonjour');

        $GLOBALS['pll_stub_current'] = null;

        expect(Settings::language())->toBeNull();
        expect(Settings::get('tagline'))->toBe('Hello');
    });

    it('reads the one value on a site with one language', function () {
        $GLOBALS['pll_stub_languages'] = [];
        $GLOBALS['pll_stub_current'] = 'fr';

        expect(Settings::get('tagline'))->toBe('Hello');
    });

    it('knows the option of a translation, so its change purges the page cache', function () {
        expect(Settings::has('tagline_fr'))->toBeTrue();
        expect(Settings::has('phone_fr'))->toBeFalse();
    });
});
