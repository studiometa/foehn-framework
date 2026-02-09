<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TimberModelDiscovery;
use Tests\Fixtures\TimberModelPostFixture;
use Tests\Fixtures\TimberModelTermFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new TimberModelDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('TimberModelDiscovery apply', function () {
    it('registers post classmap filter for post models', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TimberModelPostFixture::class));
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(1);
        expect($filters[0]['args']['hook'])->toBe('timber/post/classmap');

        // Invoke the callback to verify the classmap entry
        $callback = $filters[0]['args']['callback'];
        $map = $callback([]);

        expect($map)->toBe(['post' => TimberModelPostFixture::class]);
    });

    it('registers term classmap filter for term models', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TimberModelTermFixture::class));
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(1);
        expect($filters[0]['args']['hook'])->toBe('timber/term/classmap');

        // Invoke the callback to verify the classmap entry
        $callback = $filters[0]['args']['callback'];
        $map = $callback([]);

        expect($map)->toBe(['category' => TimberModelTermFixture::class]);
    });

    it('does not register post types or taxonomies', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TimberModelPostFixture::class));
        $this->discovery->discover($this->location, new ReflectionClass(TimberModelTermFixture::class));
        $this->discovery->apply();

        expect(wp_stub_get_calls('register_post_type'))->toBeEmpty();
        expect(wp_stub_get_calls('register_taxonomy'))->toBeEmpty();
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_filter'))->toBeEmpty();
    });

    it('registers models from cached data', function () {
        $this->discovery->restoreFromCache(['App\\' => [
            [
                'name' => 'page',
                'className' => TimberModelPostFixture::class,
                'type' => 'post',
            ],
            [
                'name' => 'tag',
                'className' => TimberModelTermFixture::class,
                'type' => 'term',
            ],
        ]]);

        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(2);
        expect($filters[0]['args']['hook'])->toBe('timber/post/classmap');
        expect($filters[1]['args']['hook'])->toBe('timber/term/classmap');
    });
});
