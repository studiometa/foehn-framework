<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Tests\Fixtures\PostTypeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new PostTypeDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('PostTypeDiscovery apply', function () {
    it('registers discovered post types with WordPress', function () {
        $this->discovery->discover($this->location, new ReflectionClass(PostTypeFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('register_post_type');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['postType'])->toBe('project');
        expect($calls[0]['args']['args']['labels']['name'])->toBe('Projects');
        expect($calls[0]['args']['args']['labels']['singular_name'])->toBe('Project');
        expect($calls[0]['args']['args']['public'])->toBeTrue();
        expect($calls[0]['args']['args']['show_in_rest'])->toBeTrue();
    });

    it('registers Timber classmap filter', function () {
        $this->discovery->discover($this->location, new ReflectionClass(PostTypeFixture::class));
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(1);
        expect($filters[0]['args']['hook'])->toBe('timber/post/classmap');
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('register_post_type'))->toBeEmpty();
    });

    it('registers post types from cached data', function () {
        $this->discovery->restoreFromCache([
            [
                'name' => 'event',
                'singular' => 'Event',
                'plural' => 'Events',
                'public' => true,
                'hasArchive' => true,
                'showInRest' => true,
                'menuIcon' => 'dashicons-calendar',
                'supports' => ['title', 'editor'],
                'taxonomies' => [],
                'rewriteSlug' => null,
                'hierarchical' => false,
                'menuPosition' => 5,
                'labels' => [],
                'rewrite' => null,
                'className' => PostTypeFixture::class,
                'implementsConfig' => false,
            ],
        ]);

        $this->discovery->apply();

        $calls = wp_stub_get_calls('register_post_type');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['postType'])->toBe('event');
        expect($calls[0]['args']['args']['has_archive'])->toBeTrue();
        expect($calls[0]['args']['args']['menu_position'])->toBe(5);
    });
});
