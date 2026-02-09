<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ShortcodeDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new ShortcodeDiscovery();
});

describe('ShortcodeDiscovery caching', function () {
    it('converts items to cacheable format', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');
        $ref->invoke($this->discovery, $this->location, [
            'tag' => 'my_shortcode',
            'className' => 'App\\Shortcodes\\MyShortcode',
            'methodName' => 'render',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(1);
        expect($cacheableData['App\\'][0])->toBe([
            'tag' => 'my_shortcode',
            'className' => 'App\\Shortcodes\\MyShortcode',
            'methodName' => 'render',
        ]);
    });

    it('handles multiple shortcodes', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');

        $ref->invoke($this->discovery, $this->location, [
            'tag' => 'gallery',
            'className' => 'App\\Shortcodes\\Gallery',
            'methodName' => 'renderGallery',
        ]);
        $ref->invoke($this->discovery, $this->location, [
            'tag' => 'button',
            'className' => 'App\\Shortcodes\\Button',
            'methodName' => 'renderButton',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(2);
        expect($cacheableData['App\\'][0]['tag'])->toBe('gallery');
        expect($cacheableData['App\\'][1]['tag'])->toBe('button');
    });

    it('can restore from cache', function () {
        $cachedData = [
            [
                'tag' => 'cached_shortcode',
                'className' => 'App\\Shortcodes\\CachedShortcode',
                'methodName' => 'handle',
            ],
        ];

        $this->discovery->restoreFromCache(['App\\' => $cachedData]);

        expect($this->discovery->wasRestoredFromCache())->toBeTrue();
    });

    it('handles shortcodes from same class', function () {
        $ref = new ReflectionMethod($this->discovery, 'addItem');

        $ref->invoke($this->discovery, $this->location, [
            'tag' => 'link',
            'className' => 'App\\Shortcodes\\LinkShortcodes',
            'methodName' => 'renderLink',
        ]);
        $ref->invoke($this->discovery, $this->location, [
            'tag' => 'external_link',
            'className' => 'App\\Shortcodes\\LinkShortcodes',
            'methodName' => 'renderExternalLink',
        ]);

        $cacheableData = $this->discovery->getCacheableData();

        expect($cacheableData['App\\'])->toHaveCount(2);
        expect($cacheableData['App\\'][0]['className'])->toBe('App\\Shortcodes\\LinkShortcodes');
        expect($cacheableData['App\\'][1]['className'])->toBe('App\\Shortcodes\\LinkShortcodes');
        expect($cacheableData['App\\'][0]['methodName'])->toBe('renderLink');
        expect($cacheableData['App\\'][1]['methodName'])->toBe('renderExternalLink');
    });
});
