<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfOptionsPageDiscovery;
use Tests\Fixtures\AcfOptionsPageFixture;
use Tests\Fixtures\AcfOptionsPageFullFixture;
use Tests\Fixtures\AcfOptionsSubPageFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    $this->discovery = new AcfOptionsPageDiscovery();
});

describe('AcfOptionsPageDiscovery::apply()', function () {
    it('registers action on acf/init hook', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfOptionsPageFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('add_action');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['hook'])->toBe('acf/init');
    });

    it('registers top-level options page with acf_add_options_page', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfOptionsPageFixture::class));
        $this->discovery->apply();

        // Simulate the acf/init hook firing
        $actionCalls = wp_stub_get_calls('add_action');
        $callback = $actionCalls[0]['args']['callback'];
        $callback();

        $calls = wp_stub_get_calls('acf_add_options_page');

        expect($calls)->toHaveCount(1);

        $config = $calls[0]['args']['config'];
        expect($config['page_title'])->toBe('Theme Settings');
        expect($config['menu_title'])->toBe('Theme');
        expect($config['menu_slug'])->toBe('theme-settings');
        expect($config['capability'])->toBe('manage_options');
        expect($config['position'])->toBe(59);
        expect($config['icon_url'])->toBe('dashicons-admin-generic');
        expect($config['redirect'])->toBeFalse();
        expect($config['autoload'])->toBeTrue();
        expect($config['post_id'])->toBe('theme-settings');
    });

    it('registers sub-page options page with acf_add_options_sub_page', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfOptionsSubPageFixture::class));
        $this->discovery->apply();

        // Simulate the acf/init hook firing
        $actionCalls = wp_stub_get_calls('add_action');
        $callback = $actionCalls[0]['args']['callback'];
        $callback();

        $calls = wp_stub_get_calls('acf_add_options_sub_page');

        expect($calls)->toHaveCount(1);

        $config = $calls[0]['args']['config'];
        expect($config['page_title'])->toBe('Social Media');
        expect($config['parent_slug'])->toBe('theme-settings');
    });

    it('registers ACF field group when class implements AcfOptionsPageInterface', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfOptionsPageFixture::class));
        $this->discovery->apply();

        // Simulate the acf/init hook firing
        $actionCalls = wp_stub_get_calls('add_action');
        $callback = $actionCalls[0]['args']['callback'];
        $callback();

        $calls = wp_stub_get_calls('acf_add_local_field_group');

        expect($calls)->toHaveCount(1);

        $group = $calls[0]['args']['group'];
        expect($group['title'])->toBe('Theme Settings');
        expect($group['location'][0][0]['param'])->toBe('options_page');
        expect($group['location'][0][0]['value'])->toBe('theme-settings');
    });

    it('does not register ACF field group when class does not implement interface', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfOptionsSubPageFixture::class));
        $this->discovery->apply();

        // Simulate the acf/init hook firing
        $actionCalls = wp_stub_get_calls('add_action');
        $callback = $actionCalls[0]['args']['callback'];
        $callback();

        $calls = wp_stub_get_calls('acf_add_local_field_group');

        expect($calls)->toBeEmpty();
    });

    it('includes updateButton and updatedMessage in config when set', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfOptionsPageFullFixture::class));
        $this->discovery->apply();

        // Simulate the acf/init hook firing
        $actionCalls = wp_stub_get_calls('add_action');
        $callback = $actionCalls[0]['args']['callback'];
        $callback();

        $calls = wp_stub_get_calls('acf_add_options_page');

        expect($calls)->toHaveCount(1);

        $config = $calls[0]['args']['config'];
        expect($config['update_button'])->toBe('Save All Settings');
        expect($config['updated_message'])->toBe('All settings have been saved.');
        expect($config['post_id'])->toBe('full_settings');
        expect($config['autoload'])->toBeFalse();
    });
});
