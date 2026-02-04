<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfBlockDiscovery;
use Tests\Fixtures\AcfBlockFixture;

beforeEach(function () {
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new AcfBlockDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('AcfBlockDiscovery apply', function () {
    it('registers acf/init action for block registration', function () {
        $this->discovery->discover(new ReflectionClass(AcfBlockFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');

        expect($actions)->toHaveCount(1);
        expect($actions[0]['args']['hook'])->toBe('acf/init');
    });

    it('registers ACF blocks when acf/init callback is invoked', function () {
        $this->discovery->discover(new ReflectionClass(AcfBlockFixture::class));
        $this->discovery->apply();

        // Simulate WordPress calling the acf/init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $blocks = wp_stub_get_calls('acf_register_block_type');

        expect($blocks)->toHaveCount(1);
        expect($blocks[0]['args']['config']['name'])->toBe('testimonial');
        expect($blocks[0]['args']['config']['title'])->toBe('Testimonial');
        expect($blocks[0]['args']['config']['category'])->toBe('formatting');
        expect($blocks[0]['args']['config']['icon'])->toBe('format-quote');
        expect($blocks[0]['args']['config']['keywords'])->toBe(['quote', 'testimonial']);

        // Default supports should be applied
        expect($blocks[0]['args']['config']['supports'])->toHaveKey('align');
        expect($blocks[0]['args']['config']['supports']['align'])->toBeFalse();
    });

    it('registers ACF field groups', function () {
        $this->discovery->discover(new ReflectionClass(AcfBlockFixture::class));
        $this->discovery->apply();

        // Trigger callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $fields = wp_stub_get_calls('acf_add_local_field_group');

        expect($fields)->toHaveCount(1);
    });

    it('registers no ACF blocks when no items discovered', function () {
        $this->discovery->apply();

        // The acf/init action is still registered, but triggering it registers no blocks
        $actions = wp_stub_get_calls('add_action');
        expect($actions)->toHaveCount(1);

        $callback = $actions[0]['args']['callback'];
        $callback();

        expect(wp_stub_get_calls('acf_register_block_type'))->toBeEmpty();
    });
});
