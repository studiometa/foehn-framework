<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Tempest\Discovery\DiscoveryLocation;

/**
 * The Composer package a discovery location belongs to.
 *
 * Tempest builds the location of a package from `vendor/composer/installed.json`:
 * one location for each PSR-4 directory, with its real path. This reads the same
 * file and maps the same real paths back to the package. A package installed from
 * a path repository with symlinks resolves outside vendor/, and still maps back.
 * The project's own directories are in no package, so they map to nothing. The app
 * directory is the project's too, even when the site's composer.json requires the
 * theme as a package: installed.json then lists it, but it is not a dependency.
 *
 * The root is the one DiscoveryLocations found, so both read the same file. The
 * loaded Composer\InstalledVersions is no help here: under WP-CLI it can be the one
 * of the WP-CLI Phar.
 */
final class InstalledPackages
{
    /** @var array<string, array{name: string, extra: array<array-key, mixed>}>|null */
    private ?array $byPath = null;

    public function __construct(
        private readonly DiscoveryLocations $locations,
    ) {}

    /**
     * The package whose PSR-4 directory is the location, or null for the project.
     *
     * @return array{name: string, extra: array<array-key, mixed>}|null
     */
    public function at(DiscoveryLocation $location): ?array
    {
        if ($location->path === $this->locations->app()?->path) {
            return null;
        }

        $this->byPath ??= $this->load();

        return $this->byPath[$location->path] ?? null;
    }

    /**
     * @return array<string, array{name: string, extra: array<array-key, mixed>}>
     */
    private function load(): array
    {
        $root = $this->locations->resolveRootPath();

        if ($root === null) {
            return [];
        }

        // resolveRootPath() only returns a directory that has this file.
        $file = $root . '/vendor/composer/installed.json';

        $installed = json_decode((string) file_get_contents($file), true);
        $packages = is_array($installed) ? $installed['packages'] ?? [] : [];
        $byPath = [];

        foreach (is_array($packages) ? $packages : [] as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null)) {
                continue;
            }

            $psr4 = $package['autoload']['psr-4'] ?? [];
            $extra = $package['extra'] ?? [];

            // install-path is relative to vendor/composer, where installed.json is.
            $packagePath = dirname($file) . '/' . ($package['install-path'] ?? '');

            foreach (is_array($psr4) ? $psr4 : [] as $directories) {
                foreach (is_array($directories) ? $directories : [$directories] as $directory) {
                    $path = is_string($directory) ? realpath($packagePath . '/' . $directory) : false;

                    if ($path !== false) {
                        $byPath[$path] = ['name' => $package['name'], 'extra' => is_array($extra) ? $extra : []];
                    }
                }
            }
        }

        return $byPath;
    }
}
