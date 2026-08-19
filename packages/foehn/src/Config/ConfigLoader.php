<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tempest\Container\Container;
use Tempest\Discovery\DiscoveryLocation;

/**
 * Loads the `*.config.php` files of every discovery location.
 *
 * A config file returns a config object, and the object is registered with
 * Tempest's container under its own class and its interfaces, which is what makes
 * `app/timber.config.php` reach whatever asks for a TimberConfig.
 *
 * Files may be named for an environment — `foehn.production.config.php` — and are
 * then read only in that environment, as reported by WordPress. A file without a
 * suffix always applies, and the environment-specific one wins over it.
 */
final readonly class ConfigLoader
{
    /**
     * Environment-specific suffixes, keyed by the environment WordPress reports.
     *
     * @var array<string, list<string>>
     */
    private const ENVIRONMENT_SUFFIXES = [
        'local' => ['.local'],
        'development' => ['.dev', '.development'],
        'staging' => ['.staging', '.stg'],
        'production' => ['.production', '.prod'],
    ];

    public function __construct(
        private Container $container,
    ) {}

    /**
     * Read every config file of the given locations and register what they return.
     *
     * @param list<DiscoveryLocation> $locations
     */
    public function load(array $locations): void
    {
        foreach ($this->find($locations) as $path) {
            $config = require $path;

            if (!is_object($config)) {
                continue;
            }

            $this->container->config($config);
        }
    }

    /**
     * The config files that apply, in the order they should be registered.
     *
     * Vendor packages come first so that a project's own files override the
     * defaults a package ships, and environment-specific files come last within
     * each location so they override the plain file next to them.
     *
     * @param list<DiscoveryLocation> $locations
     * @return list<string>
     */
    public function find(array $locations): array
    {
        $environment = $this->environment();
        $paths = [];

        foreach ($this->sortLocations($locations) as $location) {
            $found = [];

            foreach ($this->scan($location->path) as $path) {
                $suffix = $this->environmentOf($path);

                if ($suffix !== null && $suffix !== $environment) {
                    continue;
                }

                // A plain file is a default; the environment's own file refines it.
                $found[$suffix === null ? 0 : 1][] = $path;
            }

            ksort($found);

            foreach ($found as $group) {
                sort($group);
                $paths = [...$paths, ...$group];
            }
        }

        return $paths;
    }

    /**
     * @param list<DiscoveryLocation> $locations
     * @return list<DiscoveryLocation>
     */
    private function sortLocations(array $locations): array
    {
        usort(
            $locations,
            static fn(DiscoveryLocation $a, DiscoveryLocation $b): int => (int) $a->isVendor() <=> (int) $b->isVendor(),
        );

        return array_reverse($locations);
    }

    /**
     * Every `*.config.php` file under a directory.
     *
     * @return list<string>
     */
    private function scan(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $paths = [];

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!str_ends_with($file->getFilename(), '.config.php')) {
                continue;
            }

            // A package's own dependencies are not this project's configuration.
            if (str_contains($file->getPathname(), '/vendor/') && !str_starts_with($file->getPathname(), $path)) {
                continue;
            }

            $paths[] = $file->getPathname();
        }

        return $paths;
    }

    /**
     * The environment a config file is named for, or null when it applies to all.
     */
    private function environmentOf(string $path): ?string
    {
        $filename = basename($path, '.config.php');

        foreach (self::ENVIRONMENT_SUFFIXES as $environment => $suffixes) {
            foreach ($suffixes as $suffix) {
                if (str_ends_with($filename, $suffix)) {
                    return $environment;
                }
            }
        }

        return null;
    }

    /**
     * The environment WordPress reports, defaulting to production as it does.
     */
    private function environment(): string
    {
        if (!function_exists('wp_get_environment_type')) {
            return 'production';
        }

        return wp_get_environment_type();
    }
}
