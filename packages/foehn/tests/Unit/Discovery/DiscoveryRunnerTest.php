<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\CliCommandDiscovery;
use Studiometa\Foehn\Discovery\DiscoveryPhase;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Discovery\PostTypeDiscovery;
use Studiometa\Foehn\Discovery\RestRouteDiscovery;
use Tempest\Container\GenericContainer;
use Tempest\Discovery\Discovery;
use Tests\Fixtures\CustomDiscovery\DefaultPhaseFixtureDiscovery;
use Tests\Fixtures\CustomDiscovery\LateFixtureDiscovery;

describe('discovery classes are discovered', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->container = bootTestContainer();

        LateFixtureDiscovery::$applied = 0;
        DefaultPhaseFixtureDiscovery::$applied = 0;
    });

    afterEach(fn() => tearDownTestContainer());

    it('finds the framework discoveries without any of them being listed', function () {
        $discoveries = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'))->getDiscoveries();

        // Nothing enumerates these any more: they are found because they implement
        // Discovery and sit in a scanned location, exactly like a third-party one.
        expect($discoveries)->toHaveKey(HookDiscovery::class);
        expect($discoveries)->toHaveKey(PostTypeDiscovery::class);
        expect($discoveries)->toHaveKey(RestRouteDiscovery::class);
        expect($discoveries[HookDiscovery::class])->toBeInstanceOf(Discovery::class);
    });

    it('finds a discovery that ships with neither the framework nor a package', function () {
        $discoveries = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'))->getDiscoveries();

        expect($discoveries)->toHaveKey(LateFixtureDiscovery::class);
    });

    it('does not expose the pass that finds the discovery classes', function () {
        $discoveries = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'))->getDiscoveries();

        expect($discoveries)->not->toHaveKey(Tempest\Discovery\DiscoveryDiscovery::class);
    });

    it('applies a custom discovery at the phase its attribute declares', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runEarlyDiscoveries();
        $runner->runMainDiscoveries();

        expect(LateFixtureDiscovery::$applied)->toBe(0);

        $runner->runLateDiscoveries();

        expect(LateFixtureDiscovery::$applied)->toBe(1);
    });

    it('applies a discovery without the attribute in the main phase', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runEarlyDiscoveries();

        expect(DefaultPhaseFixtureDiscovery::$applied)->toBe(0);

        $runner->runMainDiscoveries();

        expect(DefaultPhaseFixtureDiscovery::$applied)->toBe(1);
    });

    it('applies each discovery once', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runLateDiscoveries();
        $runner->runLateDiscoveries();

        expect(LateFixtureDiscovery::$applied)->toBe(1);
    });

    it('registers the framework CLI commands in the early phase', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        $runner->runEarlyDiscoveries();

        // CliCommandDiscovery declares #[AsDiscovery(phase: Early)] and the framework
        // is a scanned location, so its own commands are found from the fixture app.
        expect($runner->getDiscoveries()[CliCommandDiscovery::class]->getItems())->not->toHaveCount(0);
    });
});

describe('DiscoveryRunner phase execution', function () {
    beforeEach(function () {
        wp_stub_reset();
        $this->container = bootTestContainer();
    });

    afterEach(fn() => tearDownTestContainer());

    it('runs phases and sets hasRun flag', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('CustomDiscovery'));

        expect($runner->hasRun(DiscoveryPhase::Early))->toBeFalse();
        expect($runner->hasRun(DiscoveryPhase::Main))->toBeFalse();
        expect($runner->hasRun(DiscoveryPhase::Late))->toBeFalse();

        $runner->runEarlyDiscoveries();
        expect($runner->hasRun(DiscoveryPhase::Early))->toBeTrue();
        expect($runner->hasRun(DiscoveryPhase::Main))->toBeFalse();

        $runner->runMainDiscoveries();
        expect($runner->hasRun(DiscoveryPhase::Main))->toBeTrue();
        expect($runner->hasRun(DiscoveryPhase::Late))->toBeFalse();

        $runner->runLateDiscoveries();
        expect($runner->hasRun(DiscoveryPhase::Late))->toBeTrue();
    });

    it('does not re-run a phase that has already run', function () {
        $runner = testDiscoveryRunner($this->container, testFixturePath('App'));

        $runner->runEarlyDiscoveries();

        // Reset stubs after first run
        wp_stub_reset();

        // Running again should be a no-op
        $runner->runEarlyDiscoveries();

        // No new add_action calls should have been recorded
        expect(wp_stub_get_calls('add_action'))->toBeEmpty();
    });
});

describe('DiscoveryRunner debug logging', function () {
    it('logs reflection failures when debug is enabled', function () {
        $container = new GenericContainer();
        $config = new FoehnConfig(debug: true);

        $runner = testDiscoveryRunner($container, config: $config);

        // Use reflection to access the private logDiscoveryFailure method
        $method = new ReflectionMethod($runner, 'logDiscoveryFailure');

        $warningTriggered = false;
        $warningMessage = '';

        set_error_handler(function ($errno, $errstr) use (&$warningTriggered, &$warningMessage) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
                $warningMessage = $errstr;
            }

            return true;
        });

        try {
            $exception = new ReflectionException('Class not found');
            $method->invoke($runner, 'App\\NonExistentClass', $exception);
        } finally {
            restore_error_handler();
        }

        expect($warningTriggered)->toBeTrue();
        expect($warningMessage)->toContain('[Foehn] Discovery failed for "App\\NonExistentClass"');
        expect($warningMessage)->toContain('Class not found');
    });

    it('does not log reflection failures when debug is disabled', function () {
        $container = new GenericContainer();
        $config = new FoehnConfig(debug: false);

        $runner = testDiscoveryRunner($container, config: $config);

        // Use reflection to access the private logDiscoveryFailure method
        $method = new ReflectionMethod($runner, 'logDiscoveryFailure');

        $warningTriggered = false;

        set_error_handler(function ($errno) use (&$warningTriggered) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
            }

            return true;
        });

        try {
            $exception = new ReflectionException('Class not found');
            $method->invoke($runner, 'App\\NonExistentClass', $exception);
        } finally {
            restore_error_handler();
        }

        expect($warningTriggered)->toBeFalse();
    });

    it('does not log reflection failures when config is null', function () {
        $container = new GenericContainer();

        $runner = testDiscoveryRunner($container);

        // Use reflection to access the private logDiscoveryFailure method
        $method = new ReflectionMethod($runner, 'logDiscoveryFailure');

        $warningTriggered = false;

        set_error_handler(function ($errno) use (&$warningTriggered) {
            if ($errno === E_USER_WARNING) {
                $warningTriggered = true;
            }

            return true;
        });

        try {
            $exception = new ReflectionException('Class not found');
            $method->invoke($runner, 'App\\NonExistentClass', $exception);
        } finally {
            restore_error_handler();
        }

        expect($warningTriggered)->toBeFalse();
    });
});
