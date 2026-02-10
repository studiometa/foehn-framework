<?php

declare(strict_types=1);

// Load WordPress function stubs before autoload so they're available
// when discovery classes are loaded.
require_once __DIR__ . '/wp-stubs.php';

// Load Composer autoloader
// In monorepo: vendor/ is at the repository root
// In standalone: vendor/ is at the package root
$monorepoAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
$standaloneAutoload = dirname(__DIR__) . '/vendor/autoload.php';
require_once file_exists($monorepoAutoload) ? $monorepoAutoload : $standaloneAutoload;

// Load test helper functions (Pest.php functions may not be loaded
// when running from monorepo root via phpunit.xml --configuration)
if (!function_exists('bootTestContainer')) {
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
}
