<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\AcfOptionsPageDiscovery;
use Tests\Fixtures\AcfOptionsPageFixture;

beforeEach(function () {
    wp_stub_reset();
    $this->discovery = new AcfOptionsPageDiscovery();
});

describe('AcfOptionsPageDiscovery caching', function () {
    it('converts discovered items to cacheable format', function () {
        $this->discovery->discover(new ReflectionClass(AcfOptionsPageFixture::class));

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData)->toHaveCount(1);
        expect($cacheableData[0]['className'])->toBe(AcfOptionsPageFixture::class);
        expect($cacheableData[0]['hasFields'])->toBeTrue();
        expect($cacheableData[0]['pageTitle'])->toBe('Theme Settings');
        expect($cacheableData[0]['menuTitle'])->toBe('Theme');
        expect($cacheableData[0]['menuSlug'])->toBe('theme-settings');
        expect($cacheableData[0]['capability'])->toBe('manage_options');
        expect($cacheableData[0]['position'])->toBe(59);
        expect($cacheableData[0]['parentSlug'])->toBeNull();
        expect($cacheableData[0]['iconUrl'])->toBe('dashicons-admin-generic');
        expect($cacheableData[0]['redirect'])->toBeFalse();
        expect($cacheableData[0]['postId'])->toBeNull();
        expect($cacheableData[0]['autoload'])->toBeTrue();
        expect($cacheableData[0]['updateButton'])->toBeNull();
        expect($cacheableData[0]['updatedMessage'])->toBeNull();
    });

    it('restores from cached data and applies correctly', function () {
        // Simulate cached data
        $cachedData = [
            [
                'className' => AcfOptionsPageFixture::class,
                'hasFields' => true,
                'pageTitle' => 'Cached Settings',
                'menuTitle' => 'Cached',
                'menuSlug' => 'cached-settings',
                'capability' => 'manage_options',
                'position' => 60,
                'parentSlug' => null,
                'iconUrl' => 'dashicons-admin-settings',
                'redirect' => true,
                'postId' => null,
                'autoload' => true,
                'updateButton' => null,
                'updatedMessage' => null,
            ],
        ];

        $this->discovery->restoreFromCache($cachedData);
        $this->discovery->apply();

        // Simulate the acf/init hook firing
        $actionCalls = wp_stub_get_calls('add_action');
        $callback = $actionCalls[0]['args']['callback'];
        $callback();

        $calls = wp_stub_get_calls('acf_add_options_page');

        expect($calls)->toHaveCount(1);

        $config = $calls[0]['args']['config'];
        expect($config['page_title'])->toBe('Cached Settings');
        expect($config['menu_title'])->toBe('Cached');
        expect($config['menu_slug'])->toBe('cached-settings');
        expect($config['position'])->toBe(60);
        expect($config['icon_url'])->toBe('dashicons-admin-settings');
    });

    it('reports wasRestoredFromCache correctly', function () {
        expect($this->discovery->wasRestoredFromCache())->toBeFalse();

        $this->discovery->restoreFromCache([]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });
});
