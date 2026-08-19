<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\CronDiscovery;
use Tests\Fixtures\CronCustomHookFixture;
use Tests\Fixtures\CronFixture;
use Tests\Fixtures\InvalidCronFixture;
use Tests\Fixtures\NoAttributeFixture;

beforeEach(function () {
    wp_stub_reset();
    $this->location = testDiscoveryLocation();
    $this->discovery = new CronDiscovery();
});

describe('CronDiscovery', function () {
    it('discovers cron attributes on classes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CronFixture::class));

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['className'])->toBe(CronFixture::class);
        expect($items[0]['attribute']->hook)->toBeNull();
        expect($items[0]['attribute']->intervalSeconds)->toBe(86400);
        expect($items[0]['attribute']->group)->toBe('foehn');
    });

    it('uses custom hook name when provided', function () {
        $this->discovery->discover(
            $this->location,
            new \Tempest\Reflection\ClassReflector(CronCustomHookFixture::class),
        );

        $items = iterator_to_array($this->discovery->getItems());

        expect($items)->toHaveCount(1);
        expect($items[0]['attribute']->hook)->toBe('my_plugin/sync_data');
        expect($items[0]['attribute']->intervalSeconds)->toBe(3600);
        expect($items[0]['attribute']->group)->toBe('my-plugin');
    });

    it('ignores classes without cron attributes', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(NoAttributeFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('ignores classes without __invoke method', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(InvalidCronFixture::class));

        expect($this->discovery->getItems())->toHaveCount(0);
    });

    it('reports hasItems correctly', function () {
        expect($this->discovery->getItems())->toHaveCount(0);

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CronFixture::class));

        expect($this->discovery->getItems())->not->toHaveCount(0);
    });

    it('registers action and schedules recurring action on apply', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CronFixture::class));
        $this->discovery->apply();

        // Check add_action was called for the hook
        $actionCalls = wp_stub_get_calls('add_action');
        $cronActions = array_values(array_filter(
            $actionCalls,
            fn($call) => $call['args']['hook'] === 'foehn/tests/fixtures/cron_fixture',
        ));

        expect($cronActions)->toHaveCount(1);

        // Check as_schedule_recurring_action was called
        $scheduleCalls = wp_stub_get_calls('as_schedule_recurring_action');
        expect($scheduleCalls)->toHaveCount(1);
        expect($scheduleCalls[0]['args']['hook'])->toBe('foehn/tests/fixtures/cron_fixture');
        expect($scheduleCalls[0]['args']['intervalInSeconds'])->toBe(86400);
        expect($scheduleCalls[0]['args']['group'])->toBe('foehn');
    });

    it('does not schedule when already scheduled', function () {
        $GLOBALS['wp_stub_as_has_scheduled'] = [
            'foehn/tests/fixtures/cron_fixture' => true,
        ];

        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CronFixture::class));
        $this->discovery->apply();

        // add_action should still be called (callback registration)
        $actionCalls = wp_stub_get_calls('add_action');
        $cronActions = array_values(array_filter(
            $actionCalls,
            fn($call) => $call['args']['hook'] === 'foehn/tests/fixtures/cron_fixture',
        ));
        expect($cronActions)->toHaveCount(1);

        // But as_schedule_recurring_action should NOT be called
        $scheduleCalls = wp_stub_get_calls('as_schedule_recurring_action');
        expect($scheduleCalls)->toHaveCount(0);
    });

    it('does nothing on apply when no items discovered', function () {
        $this->discovery->apply();

        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
        expect(wp_stub_get_calls('as_schedule_recurring_action'))->toBeEmpty();
    });

    it('does nothing when Action Scheduler is not available', function () {
        // Create a subclass that reports AS as unavailable
        $unavailableDiscovery = new class extends CronDiscovery {
            protected function isActionSchedulerAvailable(): bool
            {
                return false;
            }
        };

        $unavailableDiscovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CronFixture::class));
        $unavailableDiscovery->apply();

        // No actions should have been registered
        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
        expect(wp_stub_get_calls('as_schedule_recurring_action'))->toBeEmpty();
    });

    it('supports caching', function () {
        $this->discovery->discover($this->location, new \Tempest\Reflection\ClassReflector(CronFixture::class));

        expect($this->discovery->getItems()->getForLocation($this->location))->not->toBeEmpty();

        $restored = restoreThroughCacheFile($this->discovery, new CronDiscovery(), $this->location);

        expect(iterator_to_array($restored->getItems()))->toEqual(iterator_to_array($this->discovery->getItems()));
    });
});
