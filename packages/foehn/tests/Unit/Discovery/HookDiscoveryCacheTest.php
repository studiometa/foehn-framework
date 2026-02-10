<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

/**
 * Helper to add items to a discovery via reflection.
 */
function addDiscoveryItem(object $discovery, DiscoveryLocation $location, array $item): void
{
    $ref = new ReflectionMethod($discovery, 'addItem');
    $ref->invoke($discovery, $location, $item);
}

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new HookDiscovery();
});

describe('HookDiscovery caching', function () {
    it('converts action items to cacheable format', function () {
        addDiscoveryItem($this->discovery, $this->location, [
            'type' => 'action',
            'hook' => 'init',
            'className' => 'App\\Hooks\\MyHooks',
            'methodName' => 'onInit',
            'priority' => 10,
            'acceptedArgs' => 1,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0])->toBe([
            'type' => 'action',
            'hook' => 'init',
            'className' => 'App\\Hooks\\MyHooks',
            'methodName' => 'onInit',
            'priority' => 10,
            'acceptedArgs' => 1,
        ]);
    });

    it('converts filter items to cacheable format', function () {
        addDiscoveryItem($this->discovery, $this->location, [
            'type' => 'filter',
            'hook' => 'the_content',
            'className' => 'App\\Hooks\\ContentFilter',
            'methodName' => 'filterContent',
            'priority' => 20,
            'acceptedArgs' => 2,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0])->toBe([
            'type' => 'filter',
            'hook' => 'the_content',
            'className' => 'App\\Hooks\\ContentFilter',
            'methodName' => 'filterContent',
            'priority' => 20,
            'acceptedArgs' => 2,
        ]);
    });

    it('handles multiple hooks', function () {
        addDiscoveryItem($this->discovery, $this->location, [
            'type' => 'action',
            'hook' => 'init',
            'className' => 'App\\Hooks\\MultiHooks',
            'methodName' => 'onInit',
            'priority' => 5,
            'acceptedArgs' => 0,
        ]);

        addDiscoveryItem($this->discovery, $this->location, [
            'type' => 'filter',
            'hook' => 'the_title',
            'className' => 'App\\Hooks\\MultiHooks',
            'methodName' => 'filterTitle',
            'priority' => 15,
            'acceptedArgs' => 3,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(2);
        expect($cacheableData['App\\'][0]['type'])->toBe('action');
        expect($cacheableData['App\\'][1]['type'])->toBe('filter');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'type' => 'action',
                'hook' => 'wp_head',
                'className' => 'App\\Hooks\\HeadHooks',
                'methodName' => 'addMeta',
                'priority' => 1,
                'acceptedArgs' => 0,
            ],
            [
                'type' => 'filter',
                'hook' => 'body_class',
                'className' => 'App\\Hooks\\BodyHooks',
                'methodName' => 'addBodyClass',
                'priority' => 10,
                'acceptedArgs' => 1,
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles default priority and accepted args', function () {
        addDiscoveryItem($this->discovery, $this->location, [
            'type' => 'action',
            'hook' => 'save_post',
            'className' => 'App\\Hooks\\PostHooks',
            'methodName' => 'onSavePost',
            'priority' => 10,
            'acceptedArgs' => 1,
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'][0]['priority'])->toBe(10);
        expect($cacheableData['App\\'][0]['acceptedArgs'])->toBe(1);
    });
});
