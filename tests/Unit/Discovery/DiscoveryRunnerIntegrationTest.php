<?php

declare(strict_types=1);

use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\DiscoveryCache;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Discovery\HookDiscovery;
use Studiometa\Foehn\Discovery\WpDiscovery;
use Studiometa\Foehn\Hooks\Cleanup\CleanHeadTags;
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
        $tmpDir = sys_get_temp_dir() . '/foehn-test-nocache-' . uniqid();
        mkdir($tmpDir, 0o755, true);

        $config = new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::NONE, discoveryCachePath: $tmpDir);
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
        $tmpDir = sys_get_temp_dir() . '/foehn-test-cache-' . uniqid();
        mkdir($tmpDir, 0o755, true);

        $config = new FoehnConfig(discoveryCacheStrategy: DiscoveryCacheStrategy::FULL, discoveryCachePath: $tmpDir);
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

    it('discovers opt-in hook classes from config', function () {
        $config = new FoehnConfig(hooks: [CleanHeadTags::class]);

        $container = bootTestContainer();
        $container->singleton(FoehnConfig::class, fn() => $config);

        $runner = new DiscoveryRunner($container);

        // Trigger discovery via early phase
        $runner->runEarlyDiscoveries();

        $discoveries = $runner->getDiscoveries();

        /** @var HookDiscovery $hookDiscovery */
        $hookDiscovery = $discoveries[HookDiscovery::class];

        // CleanHeadTags has one #[AsAction('init')] method
        expect($hookDiscovery->hasItems())->toBeTrue();

        tearDownTestContainer();
    });

    it('skips non-existent hook classes in config', function () {
        $config = new FoehnConfig(hooks: ['NonExistent\\HookClass']);

        $container = createTestContainer();
        $container->singleton(FoehnConfig::class, fn() => $config);

        $runner = new DiscoveryRunner($container);

        // Should not throw
        $runner->runEarlyDiscoveries();

        expect($runner->hasRun('early'))->toBeTrue();
    });

    it('handles missing FoehnConfig gracefully', function () {
        $container = createTestContainer();
        // Don't register FoehnConfig — discoverOptInHooks should catch the exception

        $runner = new DiscoveryRunner($container);

        // Should not throw
        $runner->runEarlyDiscoveries();

        expect($runner->hasRun('early'))->toBeTrue();
    });
});
