<?php

declare(strict_types=1);

use Studiometa\Foehn\Attributes\AsSettingsPage;
use Studiometa\Foehn\Discovery\SettingsPageDiscovery;
use Studiometa\Foehn\Settings\Setting;
use Studiometa\Foehn\Settings\Settings;
use Tempest\Container\GenericContainer;
use Tests\Fixtures\Settings\ThemeSettingsFixture;

beforeEach(function () {
    wp_stub_reset();
    Settings::clear();

    $this->container = new GenericContainer();
    $this->location = testDiscoveryLocation();
    $this->discovery = new SettingsPageDiscovery($this->container);
});

describe('SettingsPageDiscovery caching', function () {
    it('keeps the item under its location', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class, $this->location);

        expect($this->discovery->getItems()->getForLocation($this->location))->toHaveCount(1);
    });

    it('restores the item unchanged through a cache file', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class, $this->location);

        $restored = restoreThroughCacheFile($this->discovery, new SettingsPageDiscovery($this->container));

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });

    it('restores the declarations as Setting instances, not arrays', function () {
        // The settings are read during discovery, so they go through
        // var_export() with the rest of the item.
        discoverFixture($this->discovery, ThemeSettingsFixture::class, $this->location);

        $item = restoreThroughCacheFile($this->discovery, new SettingsPageDiscovery($this->container))
            ->getItems()
            ->getForLocation($this->location)[0];

        expect($item['attribute'])->toBeInstanceOf(AsSettingsPage::class);
        expect($item['settings']['foehn_ratio'])->toBeInstanceOf(Setting::class);
        expect($item['settings']['foehn_ratio']->default)->toBe(1.5);
    });

    it('registers from the cache without calling settings() again', function () {
        discoverFixture($this->discovery, ThemeSettingsFixture::class, $this->location);

        restoreThroughCacheFile($this->discovery, new SettingsPageDiscovery($this->container))->apply();

        expect(wp_stub_get_calls('register_setting'))->toHaveCount(4);
        expect(Settings::get('foehn_posts_per_page'))->toBe(10);
    });
});
