<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use Composer\Autoload\ClassLoader;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;
use Throwable;

/**
 * Where Foehn looks for classes and config files.
 *
 * Composer's installed.json is the source of truth: it gives the project's own
 * namespaces plus every installed package that opts into discovery, which is how
 * the framework's own Twig extensions and CLI commands are found at all.
 *
 * This is separate from DiscoveryRunner because config files are read before
 * discovery runs — the config decides how discovery caches — so both need the
 * same list without one depending on the other.
 */
final class DiscoveryLocations
{
    /** @var list<DiscoveryLocation>|null */
    private ?array $locations = null;

    public function __construct(
        private readonly ?string $appPath = null,
    ) {}

    /**
     * Every location to scan.
     *
     * Tempest's own packages are dropped. They are discoverable by default —
     * that is how Tempest boots itself — but they carry no Foehn attributes, so
     * scanning them costs several hundred reflections per request and finds
     * nothing.
     *
     * @return list<DiscoveryLocation>
     */
    public function all(): array
    {
        if ($this->locations !== null) {
            return $this->locations;
        }

        $root = $this->resolveRootPath();

        if ($root === null) {
            return $this->locations = $this->fallback();
        }

        try {
            $locations = array_values(array_filter(
                DiscoveryConfig::autoload($root)->locations,
                static fn(DiscoveryLocation $location): bool => !$location->isTempest(),
            ));
        } catch (Throwable) {
            // An unreadable or absent installed.json leaves the app directory,
            // which is the one location a theme cannot do without.
            return $this->locations = $this->fallback();
        }

        // A theme whose app directory is autoloaded somewhere other than the root
        // composer.json is still expected to be discovered.
        if ($this->findApp($locations) === null) {
            $locations = [...$locations, ...$this->fallback()];
        }

        return $this->locations = $locations;
    }

    /**
     * The location the app directory belongs to.
     */
    public function app(): ?DiscoveryLocation
    {
        return $this->findApp($this->all());
    }

    /**
     * The app directory as a location of its own.
     *
     * @return list<DiscoveryLocation>
     */
    private function fallback(): array
    {
        $appPath = $this->appPath;

        if ($appPath === null || !is_dir($appPath)) {
            return [];
        }

        return [new DiscoveryLocation($this->resolveAppNamespace(), $appPath)];
    }

    /**
     * @param list<DiscoveryLocation> $locations
     */
    private function findApp(array $locations): ?DiscoveryLocation
    {
        $appPath = $this->appPath === null ? false : realpath($this->appPath);

        if ($appPath === false) {
            return null;
        }

        foreach ($locations as $location) {
            if ($location->isVendor()) {
                continue;
            }

            if (str_starts_with($appPath, $location->path)) {
                return $location;
            }
        }

        return null;
    }

    /**
     * Walk up from the app directory to the Composer root that installed it.
     */
    public function resolveRootPath(): ?string
    {
        $path = $this->appPath === null ? false : realpath($this->appPath);

        if ($path === false) {
            return null;
        }

        while ($path !== '' && $path !== dirname($path)) {
            if (is_file($path . '/composer.json') && is_file($path . '/vendor/composer/installed.json')) {
                return $path;
            }

            $path = dirname($path);
        }

        return null;
    }

    /**
     * Resolve the PSR-4 namespace Composer maps the app directory to.
     */
    private function resolveAppNamespace(): string
    {
        $appPath = $this->appPath === null ? false : realpath($this->appPath);
        $loader = $this->getComposerLoader();

        if ($appPath === false || $loader === null) {
            return 'App\\';
        }

        foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
            foreach ($dirs as $dir) {
                $dir = realpath($dir);

                if ($dir === false) {
                    continue;
                }

                if ($dir !== $appPath && !str_starts_with($appPath, $dir . '/')) {
                    continue;
                }

                // The app directory may sit below the mapped one. Its own segments
                // are part of the namespace, or every class under it would be named
                // as if it lived one directory up.
                $relative = trim(substr($appPath, strlen($dir)), '/');

                return $relative === '' ? $prefix : $prefix . str_replace('/', '\\', $relative) . '\\';
            }
        }

        return 'App\\';
    }

    /**
     * The Composer class loader of the project the app directory belongs to.
     */
    private function getComposerLoader(): ?ClassLoader
    {
        $root = $this->resolveRootPath();

        if ($root === null) {
            return null;
        }

        $autoloadFile = $root . '/vendor/autoload.php';

        if (!is_file($autoloadFile)) {
            return null;
        }

        $loader = require $autoloadFile;

        return $loader instanceof ClassLoader ? $loader : null;
    }
}
