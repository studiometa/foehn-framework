<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\HookDiscovery;
use Tests\Fixtures\HookFixture;
use Studiometa\Foehn\Discovery\DiscoveryLocation;

beforeEach(function () {
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    wp_stub_reset();
    bootTestContainer();
    $this->discovery = new HookDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('HookDiscovery apply', function () {
    it('registers discovered actions with WordPress', function () {
        $this->discovery->discover($this->location, new ReflectionClass(HookFixture::class));
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
        $this->discovery->discover($this->location, new ReflectionClass(HookFixture::class));
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

    it('registers hooks from cached data', function () {
        $this->discovery->restoreFromCache(['App\\' => [
            [
                'type' => 'action',
                'hook' => 'save_post',
                'className' => HookFixture::class,
                'methodName' => 'onInit',
                'priority' => 15,
                'acceptedArgs' => 3,
            ],
        ]]);

        $this->discovery->apply();

        $actions = wp_stub_get_calls('add_action');

        expect($actions)->toHaveCount(1);
        expect($actions[0]['args']['hook'])->toBe('save_post');
        expect($actions[0]['args']['priority'])->toBe(15);
        expect($actions[0]['args']['acceptedArgs'])->toBe(3);
    });
});
