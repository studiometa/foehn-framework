<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Settings\Settings;
use Tests\Fixtures\Settings\ThemeSettingsFixture;
use Tests\Fixtures\Settings\TopLevelSettingsFixture;
use Tests\Fixtures\Settings\TranslatableSettingsFixture;

/**
 * The arguments register_setting() was called with, keyed by option name.
 *
 * @return array<string, array<string, mixed>>
 */
function registeredSettings(): array
{
    $calls = array_column(wp_stub_get_calls('register_setting'), 'args');

    return array_combine(
        array_column($calls, 'optionName'),
        array_map(static fn(array $call): array => $call['args'] + ['group' => $call['optionGroup']], $calls),
    );
}

/**
 * Run the callback WordPress would run on admin_menu.
 */
function runAdminMenu(): void
{
    foreach (wp_stub_get_calls('add_action') as $call) {
        if ($call['args']['hook'] === 'admin_menu') {
            $call['args']['callback']();
        }
    }
}

/**
 * The HTML the page callback prints.
 */
function renderedPage(string $menu = 'add_submenu_page'): string
{
    ob_start();
    wp_stub_get_calls($menu)[0]['args']['callback']();

    return (string) ob_get_clean();
}

beforeEach(function () {
    wp_stub_reset();
    Settings::clear();

    $this->discovery = new SettingsPageDiscovery(new DiscoveryProblems());
});

describe('SettingsPageDiscovery::apply', function () {
    it('registers every declared setting', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        expect(array_keys(registeredSettings()))->toBe([
            'foehn_contact_email',
            'foehn_show_banner',
            'foehn_posts_per_page',
            'foehn_ratio',
            'foehn_layout',
        ]);
    });

    it('registers nothing when nothing was discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('register_setting'))->toBeEmpty();
        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });

    it('registers the settings under the page slug as their option group', function () {
        // options.php rejects a save whose option group does not match.
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        expect(registeredSettings()['foehn_show_banner']['group'])->toBe('theme-settings');
    });

    it('passes the type and the default through', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        $setting = registeredSettings()['foehn_posts_per_page'];

        expect($setting['type'])->toBe('integer');
        expect($setting['default'])->toBe(10);
        expect($setting['description'])->toBe('How many');
    });

    it('keeps a setting out of REST unless it asks', function () {
        // Settings are configuration and sometimes credentials, so exposure is
        // opt-in — the opposite of #[AsPostMeta].
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        expect(registeredSettings()['foehn_contact_email']['show_in_rest'])->toBeFalse();
        expect(registeredSettings()['foehn_posts_per_page']['show_in_rest'])->toBeTrue();
    });

    it('falls back to a sanitiser the type implies', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        // A setting with no sanitiser stores whatever was posted.
        expect(registeredSettings()['foehn_show_banner']['sanitize_callback'])->toBe('rest_sanitize_boolean');
        expect(registeredSettings()['foehn_posts_per_page']['sanitize_callback'])->toBe('absint');
        expect(registeredSettings()['foehn_contact_email']['sanitize_callback'])->toBe('sanitize_email');
    });

    it('keeps a select to one of its options', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        $callback = registeredSettings()['foehn_layout']['sanitize_callback'];

        expect($callback('list'))->toBe('list');
        expect($callback('<script>'))->toBe('grid');
    });

    it('resolves a sanitiser named on the page class', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        $callback = registeredSettings()['foehn_ratio']['sanitize_callback'];

        expect($callback)->toBe([ThemeSettingsFixture::class, 'clampRatio']);
        expect($callback('9'))->toBe(2.0);
    });

    it('adds the page to the admin menu, and not before admin_menu', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        // The menu does not exist on init, which is where this discovery runs.
        expect(wp_stub_get_calls('add_submenu_page'))->toBeEmpty();

        runAdminMenu();

        $page = wp_stub_get_calls('add_submenu_page')[0]['args'];

        expect($page['parentSlug'])->toBe('themes.php');
        expect($page['pageTitle'])->toBe('Theme settings');
        expect($page['menuSlug'])->toBe('theme-settings');
        expect($page['capability'])->toBe('manage_options');
    });

    it('adds a top-level page as its own menu', function () {
        discoverFixture($this->discovery, TopLevelSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        $page = wp_stub_get_calls('add_menu_page')[0]['args'];

        expect($page['menuTitle'])->toBe('Shop settings');
        expect($page['icon'])->toBe('dashicons-cart');
        expect($page['position'])->toBe(58);
        expect(wp_stub_get_calls('add_submenu_page'))->toBeEmpty();
    });
});

describe('SettingsPageDiscovery form', function () {
    it('adds one field for each setting, on admin_menu, in one untitled section', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_settings_field'))->toBeEmpty();

        runAdminMenu();

        $section = wp_stub_get_calls('add_settings_section')[0]['args'];
        $fields = array_column(wp_stub_get_calls('add_settings_field'), 'args');

        expect($section['page'])->toBe('theme-settings');
        expect($section['title'])->toBe('');
        expect(array_column($fields, 'id'))->toBe(array_keys(ThemeSettingsFixture::settings()));
        expect(array_column($fields, 'page'))->each->toBe('theme-settings');
    });

    it('labels each field, with the option name when no label was declared', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        $titles = array_column(array_column(wp_stub_get_calls('add_settings_field'), 'args'), 'title', 'id');

        expect($titles['foehn_contact_email'])->toBe('Contact email');
        expect($titles['foehn_posts_per_page'])->toBe('foehn_posts_per_page');
    });

    it('ties the row label to the field, except for a checkbox that labels itself', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        $args = array_column(array_column(wp_stub_get_calls('add_settings_field'), 'args'), 'args', 'id');

        expect($args['foehn_contact_email'])->toBe(['label_for' => 'foehn_contact_email']);
        expect($args['foehn_show_banner'])->toBe([]);
    });

    it('draws each field with its current value', function () {
        update_option('foehn_contact_email', 'hello@example.com');
        update_option('foehn_layout', 'list');

        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        $html = renderedPage();

        expect($html)
            ->toContain(
                '<input type="email" id="foehn_contact_email" name="foehn_contact_email" value="hello@example.com" class="regular-text" />',
            )
            ->toContain('<option value="list" selected="selected">List</option>')
            ->toContain('value="1.5" step="any" class="small-text"');
    });
});

describe('Settings::get', function () {
    it('answers with the declared default before anything is saved', function () {
        // get_option() answers false for an option that does not exist, whatever
        // register_setting() was told the default was.
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        expect(Settings::get('foehn_posts_per_page'))->toBe(10);
        expect(Settings::get('foehn_show_banner'))->toBeFalse();
    });

    it('answers with the stored value once there is one', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        update_option('foehn_posts_per_page', '25');

        expect(Settings::get('foehn_posts_per_page'))->toBe(25);
    });

    it('applies the declared type to what was stored', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        // WordPress stores an unchecked checkbox as the empty string, and a
        // checked one as '1'. Neither is what a boolean setting means.
        update_option('foehn_show_banner', '');

        expect(Settings::get('foehn_show_banner'))->toBeFalse();

        update_option('foehn_show_banner', '1');

        expect(Settings::get('foehn_show_banner'))->toBeTrue();
    });

    it('falls through to get_option for a setting no page declared', function () {
        expect(Settings::has('foehn_unknown'))->toBeFalse();
        expect(Settings::get('foehn_unknown', 'fallback'))->toBe('fallback');
    });

    it('lists what the pages declared', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        expect(array_keys(Settings::all()))->toContain('foehn_ratio');
        expect(Settings::has('foehn_ratio'))->toBeTrue();
    });
});

describe('SettingsPageDiscovery::apply, with translatable settings', function () {
    beforeEach(function () {
        $GLOBALS['pll_stub_languages'] = ['en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch'];
        $GLOBALS['wp_stub_is_admin'] = true;
        discoverFixture($this->discovery, TranslatableSettingsFixture::class);
        $this->discovery->apply();
    });

    afterEach(function () {
        unset($GLOBALS['wp_stub_is_admin']);
    });

    it('saves each other language in its own option group', function () {
        $groups = [];

        foreach (array_column(wp_stub_get_calls('register_setting'), 'args') as $call) {
            $groups[$call['optionGroup']][] = $call['optionName'];
        }

        expect($groups)->toBe([
            'site-texts' => ['foehn_tagline', 'foehn_phone'],
            'site-texts_fr' => ['foehn_tagline_fr', 'foehn_phone'],
            'site-texts_de' => ['foehn_tagline_de', 'foehn_phone'],
        ]);
    });

    it('edits the translation of the language selected in the admin bar', function () {
        $GLOBALS['pll_stub_current'] = 'fr';
        $GLOBALS['wp_stub_options'] = ['foehn_tagline' => 'Hello', 'foehn_tagline_fr' => 'Bonjour'];
        runAdminMenu();

        $html = renderedPage();

        expect($html)->toContain('name="option_page" value="site-texts_fr"');
        expect($html)->toContain('name="foehn_tagline_fr" value="Bonjour"');
        expect($html)->toContain('name="foehn_phone"');
        expect($html)->toContain('The translatable fields show the Français values.');
    });

    it('edits the default language and asks to select a language when all languages are selected', function () {
        $GLOBALS['wp_stub_options'] = ['foehn_tagline' => 'Hello'];
        runAdminMenu();

        $html = renderedPage();

        expect($html)->toContain('name="option_page" value="site-texts"');
        expect($html)->toContain('name="foehn_tagline" value="Hello"');
        expect($html)->toContain('Select a language in the admin bar');
    });

    it('shows an empty field for a translation that was never saved', function () {
        $GLOBALS['pll_stub_current'] = 'de';
        $GLOBALS['wp_stub_options'] = ['foehn_tagline' => 'Hello'];
        runAdminMenu();

        expect(renderedPage())->toContain('name="foehn_tagline_de" value=""');
    });
});
