<?php

declare(strict_types=1);

use Studiometa\Foehn\Discovery\CronDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryLocation;
use Tests\Fixtures\CronFixture;

beforeEach(function () {
    wp_stub_reset();
    $this->container = bootTestContainer();
    $this->location = DiscoveryLocation::app('App\\', '/tmp/test-app');
    $this->discovery = new CronDiscovery();
});

afterEach(fn() => tearDownTestContainer());

describe('CronDiscovery apply callback', function () {
    it('invokes the cron handler when the action callback fires', function () {
        // Register a trackable fixture in the container
        $invoked = false;
        $fixture = new class($invoked) {
            private bool $invoked;

            public function __construct(bool &$invoked)
            {
                $this->invoked = &$invoked;
            }

            public function __invoke(): void
            {
                $this->invoked = true;
            }
        };

        $this->container->singleton(CronFixture::class, fn() => $fixture);

        $this->discovery->discover($this->location, new ReflectionClass(CronFixture::class));
        $this->discovery->apply();

        // Capture the add_action callback and invoke it
        $actionCalls = wp_stub_get_calls('add_action');
        $cronActions = array_values(array_filter(
            $actionCalls,
            fn($call) => $call['args']['hook'] === 'foehn/tests/fixtures/cron_fixture',
        ));

        expect($cronActions)->toHaveCount(1);

        // Invoke the registered closure — this exercises the get() + $instance() path
        $callback = $cronActions[0]['args']['callback'];
        $callback();

        expect($invoked)->toBeTrue();
    });
});
