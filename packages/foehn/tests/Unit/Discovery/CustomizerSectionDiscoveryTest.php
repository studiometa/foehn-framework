<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsCustomizerSection;
use Studiometa\Foehn\Discovery\CustomizerSectionDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryProblems;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\Settings;
use Tests\Fixtures\Customizer\ContactSectionFixture;
use Tests\Fixtures\Customizer\InterfacelessSectionFixture;
use Tests\Fixtures\Customizer\SiteIdentityFixture;
use Tests\Fixtures\PostTypeFixture;

/**
 * Run the callback WordPress would run on customize_register, with a manager
 * that already has the given sections.
 *
 * @param list<string> $sections
 */
function runCustomizeRegister(array $sections = []): WP_Customize_Manager
{
    $manager = new WP_Customize_Manager($sections);

    foreach (wp_stub_get_calls('add_action') as $call) {
        if ($call['args']['hook'] === 'customize_register') {
            $call['args']['callback']($manager);
        }
    }

    return $manager;
}

beforeEach(function () {
    wp_stub_reset();
    Settings::clear();

    $this->discovery = new CustomizerSectionDiscovery(new DiscoveryProblems());
});

describe('CustomizerSectionDiscovery', function () {
    it('discovers a section and what it declares', function () {
        discoverFixture($this->discovery, ContactSectionFixture::class);

        $items = array_values(iterator_to_array($this->discovery->getItems()));

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute'])->toBeInstanceOf(AsCustomizerSection::class);
        expect($items[0]['settings']['foehn_phone'])->toBeInstanceOf(Setting::class);
    });

    it('ignores a class without the attribute', function () {
        discoverFixture($this->discovery, PostTypeFixture::class);

        expect(iterator_to_array($this->discovery->getItems()))->toBe([]);
    });

    it('reports a section that cannot say what it stores, and skips it', function () {
        $problems = rejectedProblems(
            fn(DiscoveryProblems $problems) => new CustomizerSectionDiscovery($problems),
            InterfacelessSectionFixture::class,
        );

        expect($problems[0]['reason'])->toContain('does not implement Studiometa\\Foehn\\Contracts\\SettingsInterface');
    });

    it('restores the item unchanged through a cache file', function () {
        $location = testDiscoveryLocation();
        discoverFixture($this->discovery, ContactSectionFixture::class, $location);

        $restored = restoreThroughCacheFile($this->discovery, new CustomizerSectionDiscovery(new DiscoveryProblems()));

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });
});

describe('CustomizerSectionDiscovery::apply', function () {
    it('registers the settings on init, under the section id as their option group', function () {
        // So the sanitiser also runs for `wp option update`.
        discoverFixture($this->discovery, ContactSectionFixture::class);
        $this->discovery->apply();

        $calls = array_column(wp_stub_get_calls('register_setting'), 'args');

        expect(array_column($calls, 'optionName'))->toBe(['foehn_phone', 'foehn_website', 'foehn_show_map', 'foehn_layout']);
        expect(array_column($calls, 'optionGroup'))->each->toBe('foehn_contact');
        expect(Settings::get('foehn_layout'))->toBe('grid');
    });

    it('registers nothing when nothing was discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });

    it('adds the section on customize_register, after core adds its own', function () {
        discoverFixture($this->discovery, ContactSectionFixture::class);
        $this->discovery->apply();

        $hook = wp_stub_get_calls('add_action')[0]['args'];
        $manager = runCustomizeRegister();

        expect($hook['priority'])->toBeGreaterThan(10);
        expect($manager->sections['foehn_contact'])->toBe([
            'title' => 'Contact',
            'description' => 'Shown in the footer',
            'panel' => '',
            'priority' => 30,
            'capability' => 'edit_theme_options',
        ]);
    });

    it('stores each setting as an option, with its default and capability', function () {
        discoverFixture($this->discovery, ContactSectionFixture::class);
        $this->discovery->apply();

        $setting = runCustomizeRegister()->settings['foehn_layout'];

        expect($setting['type'])->toBe('option');
        expect($setting['default'])->toBe('grid');
        expect($setting['capability'])->toBe('edit_theme_options');
    });

    it('sanitises with the setting sanitiser, and only the value', function () {
        // The Customizer passes the setting object as a second argument.
        discoverFixture($this->discovery, ContactSectionFixture::class);
        $this->discovery->apply();

        $settings = runCustomizeRegister()->settings;

        expect($settings['foehn_layout']['sanitize_callback']('nope', new stdClass()))->toBe('grid');
        expect($settings['foehn_phone']['sanitize_callback']('<b>01 23</b>', new stdClass()))->toBe('01 23');
    });

    it('adds a native control for each setting', function () {
        discoverFixture($this->discovery, ContactSectionFixture::class);
        $this->discovery->apply();

        $controls = runCustomizeRegister()->controls;

        expect($controls['foehn_phone'])->toBe([
            'label' => 'Phone',
            'description' => '',
            'section' => 'foehn_contact',
            'type' => 'tel',
            'choices' => [],
        ]);
        expect($controls['foehn_show_map']['type'])->toBe('checkbox');
        expect($controls['foehn_layout']['type'])->toBe('select');
        expect($controls['foehn_layout']['choices'])->toBe(['grid' => 'Grid', 'list' => 'List']);
        expect($controls['foehn_layout']['label'])->toBe('foehn_layout');
    });

    it('adds the fields to a section that exists, without replacing it', function () {
        discoverFixture($this->discovery, SiteIdentityFixture::class);
        $this->discovery->apply();

        $manager = runCustomizeRegister(['title_tagline']);

        expect($manager->sections['title_tagline'])->toBe(['core' => true]);
        expect($manager->controls['foehn_baseline']['section'])->toBe('title_tagline');
    });
});
