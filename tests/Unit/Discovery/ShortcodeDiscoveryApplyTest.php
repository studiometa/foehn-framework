<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\ShortcodeDiscovery;
use Tests\Fixtures\ShortcodeFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new ShortcodeDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('ShortcodeDiscovery apply', function () {
    it('registers discovered shortcodes with WordPress', function () {
        $this->discovery->discover($this->location, new ReflectionClass(ShortcodeFixture::class));
        $this->discovery->apply();

        $calls = wp_stub_get_calls('add_shortcode');

        expect($calls)->toHaveCount(2);
        expect($calls[0]['args']['tag'])->toBe('greeting');
        expect($calls[1]['args']['tag'])->toBe('farewell');
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_shortcode'))->toBeEmpty();
    });

    it('registers shortcodes from cached data', function () {
        $this->discovery->restoreFromCache(['App\\' => [
            [
                'tag' => 'cached-shortcode',
                'className' => ShortcodeFixture::class,
                'methodName' => 'greeting',
            ],
        ]]);

        $this->discovery->apply();

        $calls = wp_stub_get_calls('add_shortcode');

        expect($calls)->toHaveCount(1);
        expect($calls[0]['args']['tag'])->toBe('cached-shortcode');
    });
});
