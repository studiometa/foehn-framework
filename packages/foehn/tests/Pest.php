<?php

declare(strict_types=1);

/*
 |--------------------------------------------------------------------------
 | Test Case
 |--------------------------------------------------------------------------
 |
 | The closure you provide to your test functions is always bound to a specific
 | PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
 | You can change this by using the "uses()" function to bind a different class.
 |
 */

// uses(Tests\TestCase::class)->in('Feature');

/*
 |--------------------------------------------------------------------------
 | Expectations
 |--------------------------------------------------------------------------
 |
 | When you're writing tests, you often need to check that values meet certain
 | conditions. Pest provides a set of expectations that allow you to verify
 | that a given value matches a specific condition.
 |
 */

expect()->extend('toBeReadonly', function () {
    $reflection = new ReflectionClass($this->value);

    return $this->and($reflection->isReadonly())->toBeTrue();
});

/*
 |--------------------------------------------------------------------------
 | Functions
 |--------------------------------------------------------------------------
 |
 | While Pest is very powerful out-of-the-box, you may have some testing code
 | specific to your project that you don't want to repeat in every file.
 | Here you can define functions that can be used in all your test files.
 |
 */

/**
 * Boot a GenericContainer and set it as the global Tempest instance.
 * Returns the container for further configuration.
 */
function bootTestContainer(): \Tempest\Container\GenericContainer
{
    $container = new \Tempest\Container\GenericContainer();
    \Tempest\Container\GenericContainer::setInstance($container);
    $container->singleton(\Tempest\Container\Container::class, fn() => $container);

    return $container;
}

/**
 * Tear down the global Tempest container instance.
 */
function tearDownTestContainer(): void
{
    \Tempest\Container\GenericContainer::setInstance(null);
}

/**
 * Restore a discovery from what another one would have written to the cache.
 *
 * The data goes through `var_export()` and `require`, the same path DiscoveryCache
 * takes, so a value that cannot survive a cache file fails here rather than in
 * production. Returns the target for chaining.
 *
 * @template T of \Studiometa\Foehn\Discovery\WpDiscovery
 * @param T $target A fresh discovery to restore into
 * @return T
 */
function restoreThroughCacheFile(
    \Studiometa\Foehn\Discovery\WpDiscovery $source,
    \Studiometa\Foehn\Discovery\WpDiscovery $target,
): \Studiometa\Foehn\Discovery\WpDiscovery {
    $file = tempnam(sys_get_temp_dir(), 'foehn-cache-') . '.php';

    try {
        file_put_contents($file, '<?php return ' . var_export($source->getCacheableData(), true) . ';');

        /** @var array<string, list<array<string, mixed>>> $data */
        $data = require $file;
    } finally {
        @unlink($file);
    }

    $target->restoreFromCache($data);

    return $target;
}

/**
 * Run a discovery over a fixture class, the way DiscoveryRunner does.
 *
 * Tests seed items through the discover() interface rather than reaching into the
 * protected addItem(), so what they exercise is what production calls.
 *
 * @param class-string $fixture
 */
function discoverFixture(
    \Studiometa\Foehn\Discovery\WpDiscovery $discovery,
    string $fixture,
    ?\Studiometa\Foehn\Discovery\DiscoveryLocation $location = null,
): \Studiometa\Foehn\Discovery\DiscoveryLocation {
    $location ??= \Studiometa\Foehn\Discovery\DiscoveryLocation::app('App\\', '/tmp/test-app');

    $discovery->discover($location, new ReflectionClass($fixture));

    return $location;
}
