<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Tests\Fixtures\TaxonomyFixture;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new TaxonomyDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('TaxonomyDiscovery apply', function () {
    it('registers discovered taxonomies with WordPress', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TaxonomyFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('register_taxonomy');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['taxonomy'])->toBe('project_category');
        expect($calls[0]['args']['objectType'])->toBe(['project']);
        expect($calls[0]['args']['args']['labels']['name'])->toBe('Categories');
        expect($calls[0]['args']['args']['labels']['singular_name'])->toBe('Category');
        expect($calls[0]['args']['args']['hierarchical'])->toBeTrue();
    });

    it('registers Timber classmap filter', function () {
        $this->discovery->discover($this->location, new ReflectionClass(TaxonomyFixture::class));
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(1);
        expect($filters[0]['args']['hook'])->toBe('timber/term/classmap');
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('register_taxonomy'))->toBeEmpty();
    });

    it('registers the same taxonomy whether scanned or restored from cache', function () {
        $scanned = new TaxonomyDiscovery();
        discoverFixture($scanned, TaxonomyFixture::class, $this->location);

        $scanned->apply();
        $scannedArgs = wp_stub_get_calls('register_taxonomy')[0]['args'];

        wp_stub_reset();

        restoreThroughCacheFile($scanned, $this->discovery)->apply();

        $calls = wp_stub_get_calls('register_taxonomy');

        expect($calls)->toHaveCount(1)->and($calls[0]['args'])->toBe($scannedArgs);
    });
});
