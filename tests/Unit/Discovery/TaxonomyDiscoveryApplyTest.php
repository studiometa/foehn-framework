<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\TaxonomyDiscovery;
use Tests\Fixtures\TaxonomyFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

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

    it('registers taxonomies from cached data', function () {
        $this->discovery->restoreFromCache([
            [
                'name' => 'genre',
                'singular' => 'Genre',
                'plural' => 'Genres',
                'postTypes' => ['movie'],
                'public' => true,
                'hierarchical' => true,
                'showInRest' => true,
                'showAdminColumn' => true,
                'rewriteSlug' => null,
                'labels' => [],
                'rewrite' => null,
                'className' => TaxonomyFixture::class,
                'implementsConfig' => false,
            ],
        ]);

        $this->discovery->apply();

        $calls = wp_stub_get_calls('register_taxonomy');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['taxonomy'])->toBe('genre');
        expect($calls[0]['args']['objectType'])->toBe(['movie']);
    });
});
