<?php

declare(strict_types=1);

use Studiometa\WPTempest\Config\WpTempestConfig;
use Studiometa\WPTempest\Discovery\DiscoveryCache;
use Studiometa\WPTempest\Discovery\DiscoveryRunner;
use Studiometa\WPTempest\Discovery\HookDiscovery;
use Studiometa\WPTempest\Discovery\WpDiscovery;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Core\DiscoveryCacheStrategy;

function createTestContainer(): GenericContainer
{
    $container = new GenericContainer();
    $container->singleton(Container::class, fn() => $container);

    return $container;
}

describe('DiscoveryRunner integration', function () {
    it('hasRun returns false by default for all phases', function () {
        $container = createTestContainer();
        $runner = new DiscoveryRunner($container);

        expect($runner->hasRun('early'))->toBeFalse();
        expect($runner->hasRun('main'))->toBeFalse();
        expect($runner->hasRun('late'))->toBeFalse();
    });

    it('returns false for unknown phase', function () {
        $container = createTestContainer();
        $runner = new DiscoveryRunner($container);

        expect($runner->hasRun('unknown'))->toBeFalse();
    });

    it('starts with empty discoveries', function () {
        $container = createTestContainer();
        $runner = new DiscoveryRunner($container);

        expect($runner->getDiscoveries())->toBeEmpty();
    });

    it('initializes all discoveries during ensureDiscovered via early phase', function () {
        $container = createTestContainer();
        $runner = new DiscoveryRunner($container);

        // Early phase triggers ensureDiscovered which initializes all discovery instances
        $runner->runEarlyDiscoveries();

        $discoveries = $runner->getDiscoveries();

        expect($discoveries)->toHaveCount(count(DiscoveryRunner::getAllDiscoveryClasses()));

        foreach (DiscoveryRunner::getAllDiscoveryClasses() as $class) {
            expect($discoveries)->toHaveKey($class);
            expect($discoveries[$class])->toBeInstanceOf(WpDiscovery::class);
        }
    });

    it('does not scan classes when no app path is given', function () {
        $container = createTestContainer();
        $runner = new DiscoveryRunner($container);

        $runner->runEarlyDiscoveries();

        // All discoveries should exist but have no items (no classes to scan)
        foreach ($runner->getDiscoveries() as $discovery) {
            expect($discovery->hasItems())->toBeFalse();
        }
    });

    it('early phase is idempotent', function () {
        $container = createTestContainer();
        $runner = new DiscoveryRunner($container);

        $runner->runEarlyDiscoveries();
        $runner->runEarlyDiscoveries(); // should not throw

        expect($runner->hasRun('early'))->toBeTrue();
    });

    it('does not restore from cache when cache is disabled', function () {
        $tmpDir = sys_get_temp_dir() . '/wp-tempest-test-nocache-' . uniqid();
        mkdir($tmpDir, 0o755, true);

        $config = new WpTempestConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::NONE,
            discoveryCachePath: $tmpDir,
        );
        $cache = new DiscoveryCache($config);

        $container = createTestContainer();
        $runner = new DiscoveryRunner($container, $cache);
        $runner->runEarlyDiscoveries();

        $discoveries = $runner->getDiscoveries();

        /** @var HookDiscovery $hookDiscovery */
        $hookDiscovery = $discoveries[HookDiscovery::class];
        expect($hookDiscovery->wasRestoredFromCache())->toBeFalse();

        // Clean up
        rmdir($tmpDir);
    });

    it('restores discoveries from cache via ensureDiscovered', function () {
        // Use reflection to call ensureDiscovered directly (without apply)
        $tmpDir = sys_get_temp_dir() . '/wp-tempest-test-cache-' . uniqid();
        mkdir($tmpDir, 0o755, true);

        $config = new WpTempestConfig(
            discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
            discoveryCachePath: $tmpDir,
        );
        $cache = new DiscoveryCache($config);

        $cache->store([
            HookDiscovery::class => [
                [
                    'type' => 'action',
                    'hook' => 'init',
                    'className' => 'App\\Hooks\\Test',
                    'methodName' => 'onInit',
                    'priority' => 10,
                    'acceptedArgs' => 1,
                ],
            ],
        ]);
        $cache->storeStrategy(DiscoveryCacheStrategy::FULL);

        $container = createTestContainer();
        $runner = new DiscoveryRunner($container, $cache);

        // Call ensureDiscovered via reflection to test cache restoration
        // without triggering apply() which needs WordPress functions
        $method = new ReflectionMethod($runner, 'ensureDiscovered');
        $method->invoke($runner);

        $discoveries = $runner->getDiscoveries();

        /** @var HookDiscovery $hookDiscovery */
        $hookDiscovery = $discoveries[HookDiscovery::class];
        expect($hookDiscovery->wasRestoredFromCache())->toBeTrue();

        // Clean up
        array_map('unlink', glob($tmpDir . '/*'));
        rmdir($tmpDir);
    });
});
