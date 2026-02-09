<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfFieldGroupDiscovery;
use Tests\Fixtures\AcfFieldGroupComplexLocationFixture;
use Tests\Fixtures\AcfFieldGroupFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new AcfFieldGroupDiscovery();
});

afterEach(fn () => tearDownTestContainer());

describe('AcfFieldGroupDiscovery apply', function () {
    it('registers acf/init action for field group registration', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfFieldGroupFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');

        expect($actions)->toHaveCount(1);
        expect($actions[0]['args']['hook'])->toBe('acf/init');
    });

    it('registers ACF field groups when acf/init callback is invoked', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfFieldGroupFixture::class));
        $this->discovery->apply();

        // Simulate WordPress calling the acf/init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $fieldGroups = wp_stub_get_calls('acf_add_local_field_group');

        expect($fieldGroups)->toHaveCount(1);

        $config = $fieldGroups[0]['args']['group'];
        expect($config['title'])->toBe('Property Details');
        expect($config['position'])->toBe('acf_after_title');
        expect($config['menu_order'])->toBe(0);
        expect($config['style'])->toBe('seamless');
        expect($config['label_placement'])->toBe('left');
        expect($config['instruction_placement'])->toBe('field');
        expect($config['hide_on_screen'])->toBe(['the_content', 'excerpt']);
    });

    it('registers no field groups when no items discovered', function () {
        $this->discovery->apply();

        // The acf/init action is still registered, but triggering it registers no field groups
        $actions = wp_stub_get_calls('add_action');
        expect($actions)->toHaveCount(1);

        $callback = $actions[0]['args']['callback'];
        $callback();

        expect(wp_stub_get_calls('acf_add_local_field_group'))->toBeEmpty();
    });

    it('sets location rules correctly for simplified format', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfFieldGroupFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $fieldGroups = wp_stub_get_calls('acf_add_local_field_group');
        $config = $fieldGroups[0]['args']['group'];

        // Location should be set (checking that it exists and has correct structure)
        expect($config)->toHaveKey('location');
        expect($config['location'])->toBeArray();
    });

    it('registers field groups from cached data', function () {
        $cachedData = [
            [
                'name' => 'cached_fields',
                'title' => 'Cached Fields',
                'location' => ['post_type' => 'page'],
                'position' => 'normal',
                'menuOrder' => 5,
                'style' => 'default',
                'labelPlacement' => 'top',
                'instructionPlacement' => 'label',
                'hideOnScreen' => [],
                'className' => AcfFieldGroupFixture::class,
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $fieldGroups = wp_stub_get_calls('acf_add_local_field_group');

        expect($fieldGroups)->toHaveCount(1);

        $config = $fieldGroups[0]['args']['group'];
        expect($config['title'])->toBe('Cached Fields');
        expect($config['position'])->toBe('normal');
        expect($config['menu_order'])->toBe(5);
        expect($config['style'])->toBe('default');
    });

    it('handles complex location rules with OR and AND conditions', function () {
        $this->discovery->discover($this->location, new ReflectionClass(AcfFieldGroupComplexLocationFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $fieldGroups = wp_stub_get_calls('acf_add_local_field_group');

        expect($fieldGroups)->toHaveCount(1);

        $config = $fieldGroups[0]['args']['group'];
        expect($config['title'])->toBe('Complex Fields');
        expect($config)->toHaveKey('location');
        // The location should have been parsed and set correctly
        expect($config['location'])->toBeArray();
    });

    it('does not include hide_on_screen when empty', function () {
        $cachedData = [
            [
                'name' => 'no_hide_fields',
                'title' => 'No Hide Fields',
                'location' => ['post_type' => 'post'],
                'position' => 'normal',
                'menuOrder' => 0,
                'style' => 'default',
                'labelPlacement' => 'top',
                'instructionPlacement' => 'label',
                'hideOnScreen' => [],
                'className' => AcfFieldGroupFixture::class,
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $fieldGroups = wp_stub_get_calls('acf_add_local_field_group');
        $config = $fieldGroups[0]['args']['group'];

        // hide_on_screen should not be set when empty
        expect($config)->not->toHaveKey('hide_on_screen');
    });
});
