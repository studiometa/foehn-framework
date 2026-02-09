<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockDiscovery;
use Tests\Fixtures\BlockFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new BlockDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('BlockDiscovery apply', function () {
    it('registers init action for block registration', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');

        expect($actions)->toHaveCount(1);
        expect($actions[0]['args']['hook'])->toBe('init');
    });

    it('registers blocks when init callback is invoked', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));
        $this->discovery->apply();

        // Simulate WordPress calling the init callback
        $actions = wp_stub_get_calls('add_action');
        $callback = $actions[0]['args']['callback'];
        $callback();

        $blocks = wp_stub_get_calls('register_block_type');

        expect($blocks)->toHaveCount(1);
        expect($blocks[0]['args']['blockName'])->toBe('test/hero');
        expect($blocks[0]['args']['args']['title'])->toBe('Hero Block');
        expect($blocks[0]['args']['args']['category'])->toBe('design');
        expect($blocks[0]['args']['args']['icon'])->toBe('cover-image');
        expect($blocks[0]['args']['args']['description'])->toBe('A hero block.');
        expect($blocks[0]['args']['args']['keywords'])->toBe(['hero', 'banner']);
    });

    it('registers no blocks when no items discovered', function () {
        $this->discovery->apply();

        // The init action is still registered, but triggering it registers no blocks
        $actions = wp_stub_get_calls('add_action');
        expect($actions)->toHaveCount(1);

        $callback = $actions[0]['args']['callback'];
        $callback();

        expect(wp_stub_get_calls('register_block_type'))->toBeEmpty();
    });
});
