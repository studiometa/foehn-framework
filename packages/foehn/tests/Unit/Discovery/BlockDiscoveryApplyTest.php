<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\BlockDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\BlockFixture;
use Tests\Fixtures\ContainerBlockFixture;

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

    it('disables the html support so "Edit as HTML" cannot invalidate a dynamic block', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));
        $this->discovery->apply();

        $callback = wp_stub_get_calls('add_action')[0]['args']['callback'];
        $callback();

        // BlockFixture declares no supports at all, so the seed is the only source.
        expect(wp_stub_get_calls('register_block_type')[0]['args']['args']['supports'])->toBe(['html' => false]);
    });

    it('registers blocks with the block API version 3', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));
        $this->discovery->apply();

        $callback = wp_stub_get_calls('add_action')[0]['args']['callback'];
        $callback();

        $blocks = wp_stub_get_calls('register_block_type');

        expect($blocks[0]['args']['args']['api_version'])->toBe(3);
    });

    it('passes allowed_blocks for a container block', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ContainerBlockFixture::class));
        $this->discovery->apply();

        $callback = wp_stub_get_calls('add_action')[0]['args']['callback'];
        $callback();

        $args = wp_stub_get_calls('register_block_type')[0]['args']['args'];

        expect($args['allowed_blocks'])->toBe(['core/heading', 'core/paragraph']);
        // The template and the template lock are editor-side only.
        expect($args)->not->toHaveKey('template');
        expect($args)->not->toHaveKey('template_lock');
    });

    it('omits allowed_blocks for a non container block', function () {
        $this->discovery->discover($this->location, new ReflectionClass(BlockFixture::class));
        $this->discovery->apply();

        $callback = wp_stub_get_calls('add_action')[0]['args']['callback'];
        $callback();

        expect(wp_stub_get_calls('register_block_type')[0]['args']['args'])->not->toHaveKey('allowed_blocks');
    });

    it('registers attributes without the editor only keys', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ContainerBlockFixture::class));
        $this->discovery->apply();

        $callback = wp_stub_get_calls('add_action')[0]['args']['callback'];
        $callback();

        $attributes = wp_stub_get_calls('register_block_type')[0]['args']['args']['attributes'];

        expect($attributes['ctaLabel'])->toBe(['type' => 'string', 'default' => 'Read more']);
        expect($attributes['variant'])->toBe(['type' => 'string']);
        expect($attributes['image_id'])->toBe(['type' => 'integer']);
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
