<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Tests\Fixtures\PostTypeFixture;

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

    it('registers the same post type whether scanned or restored from cache', function () {
        $scanned = new PostTypeDiscovery();
        discoverFixture($scanned, PostTypeFixture::class, $this->location);

        $scanned->apply();
        $scannedArgs = wp_stub_get_calls('register_post_type')[0]['args'];

        wp_stub_reset();

        restoreThroughCacheFile($scanned, $this->discovery)->apply();

        $calls = wp_stub_get_calls('register_post_type');

        expect($calls)->toHaveCount(1)->and($calls[0]['args'])->toBe($scannedArgs);
    });
});
