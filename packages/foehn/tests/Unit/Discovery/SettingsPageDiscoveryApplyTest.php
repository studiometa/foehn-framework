<?php

declare(strict_types=1);

use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Settings\Settings;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\Settings\ThemeSettingsFixture;
use Tests\Fixtures\Settings\TopLevelSettingsFixture;

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
 * A view engine that records what it was asked to render.
 */
function recordingViewEngine(): ViewEngineInterface
{
    return new class implements ViewEngineInterface {
        /** @var list<array{template: string, context: array<string, mixed>|object}> */
        public static array $rendered = [];

        public function render(string $template, array|object $context = []): string
        {
            self::$rendered[] = ['template' => $template, 'context' => $context];

            return '<p class="twig">rendered ' . $template . '</p>';
        }

        public function renderFirst(array $templates, array|object $context = []): string
        {
            return $this->render($templates[0] ?? '', $context);
        }

        public function exists(string $template): bool
        {
            return true;
        }

        public function share(string $key, mixed $value): void {}

        public function getShared(): array
        {
            return [];
        }
    };
}

beforeEach(function () {
    wp_stub_reset();
    Settings::clear();

    ThemeSettingsFixture::$rendered = 0;

    $this->view = recordingViewEngine();
    $this->view::$rendered = [];

    $this->container = new GenericContainer();
    $this->container->singleton(ViewEngineInterface::class, fn() => $this->view);

    $this->discovery = new SettingsPageDiscovery($this->container);
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
    });

    it('takes a declared function name over the default', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();

        expect(registeredSettings()['foehn_contact_email']['sanitize_callback'])->toBe('sanitize_email');
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

describe('SettingsPageDiscovery form body', function () {
    it('renders a declared template through the view engine', function () {
        discoverFixture($this->discovery, TopLevelSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        ob_start();
        wp_stub_get_calls('add_menu_page')[0]['args']['callback']();
        $html = (string) ob_get_clean();

        expect($this->view::$rendered[0]['template'])->toBe('settings/shop');
        expect($html)->toContain('rendered settings/shop');
    });

    it('gives the template the current values, typed as the page declared them', function () {
        update_option('foehn_currency', 'GBP');

        discoverFixture($this->discovery, TopLevelSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        ob_start();
        wp_stub_get_calls('add_menu_page')[0]['args']['callback']();
        ob_end_clean();

        $context = $this->view::$rendered[0]['context'];

        expect($context['settings'])->toBe(['foehn_currency' => 'GBP']);
        expect($context['page'])->toBe(['slug' => 'shop-settings', 'title' => 'Shop']);
    });

    it('gives the template the declared default before anything is saved', function () {
        discoverFixture($this->discovery, TopLevelSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        ob_start();
        wp_stub_get_calls('add_menu_page')[0]['args']['callback']();
        ob_end_clean();

        expect($this->view::$rendered[0]['context']['settings'])->toBe(['foehn_currency' => 'EUR']);
    });

    it('asks the page rather than the view engine when it builds its own form', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        ob_start();
        wp_stub_get_calls('add_submenu_page')[0]['args']['callback']();
        $html = (string) ob_get_clean();

        expect($html)->toContain('the form fields');
        expect(ThemeSettingsFixture::$rendered)->toBe(1);
        expect($this->view::$rendered)->toBe([]);
    });
});

describe('SettingsPageDiscovery page shell', function () {
    it('wraps the page own fields in a form options.php accepts', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        ob_start();
        wp_stub_get_calls('add_submenu_page')[0]['args']['callback']();
        $html = (string) ob_get_clean();

        expect($html)->toContain('<form action="options.php" method="post">');
        expect($html)->toContain('<h1>Theme settings</h1>');
        expect($html)->toContain('the form fields');
        expect(ThemeSettingsFixture::$rendered)->toBe(1);
    });

    it('prints the nonce the page cannot forget because it never writes it', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        ob_start();
        wp_stub_get_calls('add_submenu_page')[0]['args']['callback']();
        ob_end_clean();

        // Without settings_fields() the save is rejected, and the page would
        // look like it simply did not work.
        expect(wp_stub_get_calls('settings_fields')[0]['args']['optionGroup'])->toBe('theme-settings');
        expect(wp_stub_get_calls('do_settings_sections'))->toHaveCount(1);
        expect(wp_stub_get_calls('submit_button'))->toHaveCount(1);
        expect(wp_stub_get_calls('settings_errors'))->toHaveCount(1);
    });

    it('escapes the title it prints', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class);
        $this->discovery->apply();
        runAdminMenu();

        ob_start();
        wp_stub_get_calls('add_submenu_page')[0]['args']['callback']();
        $html = (string) ob_get_clean();

        expect($html)->not->toContain('<h1><script');
        expect($html)->toContain(esc_html('Theme settings'));
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
