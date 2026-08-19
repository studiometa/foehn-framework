<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\HookDiscovery;
use Tests\Fixtures\HookFixture;

beforeEach(function () {
    $this->location = testDiscoveryLocation();
    wp_stub_reset();
    $this->container = bootTestContainer();
    $this->discovery = new HookDiscovery($this->container);
});

afterEach(fn() => tearDownTestContainer());

describe('HookDiscovery apply', function () {
    it('registers discovered actions with WordPress', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(HookFixture::class));
        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');

        expect($actions)->toHaveCount(2);
        expect($actions[0]['args']['hook'])->toBe('init');
        expect($actions[0]['args']['priority'])->toBe(10);
        expect($actions[0]['args']['acceptedArgs'])->toBe(1);

        expect($actions[1]['args']['hook'])->toBe('wp_head');
        expect($actions[1]['args']['priority'])->toBe(5);
        expect($actions[1]['args']['acceptedArgs'])->toBe(0);
    });

    it('registers discovered filters with WordPress', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(HookFixture::class));
        $this->discovery->apply();

        $filters = wp_stub_get_calls('add_filter');

        expect($filters)->toHaveCount(2);
        expect($filters[0]['args']['hook'])->toBe('the_content');
        expect($filters[0]['args']['priority'])->toBe(10);

        expect($filters[1]['args']['hook'])->toBe('the_title');
        expect($filters[1]['args']['priority'])->toBe(20);
        expect($filters[1]['args']['acceptedArgs'])->toBe(2);
    });

    it('registers nothing when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
        expect(wp_stub_get_calls('add_filter'))->toBeEmpty();
    });

    it('registers the same hooks whether scanned or restored from cache', function () {
        $scanned = new HookDiscovery($this->container);
        discoverFixture($scanned, HookFixture::class, $this->location);

        $scannedActions = [];
        $scanned->apply();

        foreach (wp_stub_get_calls('add_action') as $call) {
            $scannedActions[] = [
                'hook' => $call['args']['hook'],
                'priority' => $call['args']['priority'],
                'acceptedArgs' => $call['args']['acceptedArgs'],
            ];
        }

        wp_stub_reset();

        restoreThroughCacheFile($scanned, $this->discovery)->apply();

        $restoredActions = [];

        foreach (wp_stub_get_calls('add_action') as $call) {
            $restoredActions[] = [
                'hook' => $call['args']['hook'],
                'priority' => $call['args']['priority'],
                'acceptedArgs' => $call['args']['acceptedArgs'],
            ];
        }

        expect($restoredActions)->toBe($scannedActions)->and($restoredActions)->not->toBeEmpty();
    });
});
