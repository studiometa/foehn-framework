<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

use ReflectionClass;
use Studiometa\Foehn\Config\FoehnConfig;
use Tempest\Container\Container;

/**
 * Orchestrates the discovery process across WordPress lifecycle phases.
 *
 * This runner fully owns the discovery lifecycle:
 * 1. Scans classes using Composer's PSR-4 autoload map
 * 2. Calls discover() on each WpDiscovery for every class found
 * 3. Calls apply() at the correct WordPress hook timing
 *
 * Tempest is used only for the DI container, not for discovery.
 */
final class DiscoveryRunner
{
    /** @var array<class-string<WpDiscovery>, WpDiscovery> */
    private array $discoveries = [];

    /** @var array<string, array<int, array<string, mixed>>>|null */
    private ?array $cachedData = null;

    private bool $cacheLoaded = false;
    private bool $discovered = false;
    private bool $earlyRan = false;
    private bool $mainRan = false;
    private bool $lateRan = false;

    public function __construct(
        private readonly Container $container,
        private readonly ?DiscoveryCache $cache = null,
        private readonly ?string $appPath = null,
        private readonly ?FoehnConfig $config = null,
    ) {}

    /**
     * Run early discoveries (after_setup_theme).
     * These run before most WordPress initialization.
     */
    public function runEarlyDiscoveries(): void
    {
        if ($this->earlyRan) {
            return;
        }

        $this->ensureDiscovered();

        // Hook discovery runs early to catch after_setup_theme hooks
        $this->applyDiscovery(HookDiscovery::class);

        // Shortcodes can be registered early
        $this->applyDiscovery(ShortcodeDiscovery::class);

        // CLI commands are registered early so they're available immediately
        $this->applyDiscovery(CliCommandDiscovery::class);

        // Timber class maps need to be available before queries
        $this->applyDiscovery(TimberModelDiscovery::class);

        $this->earlyRan = true;
    }

    /**
     * Run main discoveries (init).
     * Post types, taxonomies, and blocks are registered here.
     */
    public function runMainDiscoveries(): void
    {
        if ($this->mainRan) {
            return;
        }

        $this->ensureDiscovered();

        // Post types and taxonomies
        $this->applyDiscovery(PostTypeDiscovery::class);
        $this->applyDiscovery(TaxonomyDiscovery::class);

        // Menus
        $this->applyDiscovery(MenuDiscovery::class);

        // Blocks
        $this->applyDiscovery(AcfBlockDiscovery::class);
        $this->applyDiscovery(BlockDiscovery::class);

        // Block patterns
        $this->applyDiscovery(BlockPatternDiscovery::class);

        $this->mainRan = true;
    }

    /**
     * Run late discoveries (wp_loaded).
     * Template controllers and REST routes are registered here.
     */
    public function runLateDiscoveries(): void
    {
        if ($this->lateRan) {
            return;
        }

        $this->ensureDiscovered();

        // View composers and template controllers
        $this->applyDiscovery(ViewComposerDiscovery::class);
        $this->applyDiscovery(TemplateControllerDiscovery::class);

        // REST API routes
        $this->applyDiscovery(RestRouteDiscovery::class);

        $this->lateRan = true;
    }

    /**
     * Ensure all classes have been scanned and discoveries populated.
     */
    private function ensureDiscovered(): void
    {
        if ($this->discovered) {
            return;
        }

        $this->discovered = true;
        $this->loadCache();

        // Initialize all discovery instances
        foreach (self::getAllDiscoveryClasses() as $discoveryClass) {
            if (isset($this->discoveries[$discoveryClass])) {
                continue;
            }

            $this->discoveries[$discoveryClass] = $this->container->get($discoveryClass);
        }

        // If we have cached data, restore discoveries from cache
        if ($this->cachedData !== null) {
            foreach ($this->discoveries as $className => $discovery) {
                if (!isset($this->cachedData[$className]) || !method_exists($discovery, 'restoreFromCache')) {
                    continue;
                }

                $discovery->restoreFromCache($this->cachedData[$className]);
            }

            return;
        }

        // No cache — scan classes and run discover()
        $classes = $this->scanClasses();

        foreach ($classes as $class) {
            foreach ($this->discoveries as $discovery) {
                $discovery->discover($class);
            }
        }

        // Also discover opt-in hook classes from config
        $this->discoverOptInHooks();
    }

    /**
     * Discover opt-in hook classes from FoehnConfig.
     */
    private function discoverOptInHooks(): void
    {
        try {
            $config = $this->container->get(\Studiometa\Foehn\Config\FoehnConfig::class);
        } catch (\Throwable) {
            return;
        }

        foreach ($config->hooks as $hookClass) {
            if (!class_exists($hookClass)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($hookClass);

                foreach ($this->discoveries as $discovery) {
                    $discovery->discover($reflection);
                }
            } catch (\ReflectionException $e) {
                $this->logDiscoveryFailure($hookClass, $e);

                continue;
            }
        }
    }

    /**
     * Apply a specific discovery.
     *
     * @param class-string<WpDiscovery> $discoveryClass
     */
    private function applyDiscovery(string $discoveryClass): void
    {
        if (!isset($this->discoveries[$discoveryClass])) {
            return;
        }

        $this->discoveries[$discoveryClass]->apply();
    }

    /**
     * Scan classes from the app directory using Composer's PSR-4 autoload map.
     *
     * @return array<ReflectionClass<object>>
     */
    private function scanClasses(): array
    {
        if ($this->appPath === null) {
            return [];
        }

        $classes = [];
        $appPath = realpath($this->appPath);

        if ($appPath === false) {
            return [];
        }

        // Find all PHP files in the app directory
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $appPath,
            \RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        // Get Composer's class loader to resolve class names
        $classMap = $this->buildClassMapFromAutoload($appPath);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filePath = $file->getRealPath();

            if ($filePath === false) {
                continue;
            }

            // Try to find the class name from the class map
            $className = $classMap[$filePath] ?? $this->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            if (!class_exists($className)) {
                continue;
            }

            try {
                $reflection = new ReflectionClass($className);

                // Skip abstract classes and interfaces
                if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                    continue;
                }

                $classes[] = $reflection;
            } catch (\ReflectionException $e) {
                $this->logDiscoveryFailure($className, $e);

                continue;
            }
        }

        return $classes;
    }

    /**
     * Build a file path to class name map from Composer's PSR-4 autoload.
     *
     * @return array<string, string> Map of file path => class name
     */
    private function buildClassMapFromAutoload(string $appPath): array
    {
        $map = [];

        // Try to get the Composer autoloader
        $autoloadFiles = [
            dirname($appPath) . '/vendor/autoload.php',
            $appPath . '/../../vendor/autoload.php',
            $appPath . '/../../../vendor/autoload.php',
        ];

        $loader = null;

        foreach ($autoloadFiles as $autoloadFile) {
            $resolved = realpath($autoloadFile);

            if ($resolved !== false && file_exists($resolved)) {
                $loader = require $resolved;

                break;
            }
        }

        if ($loader === null || !$loader instanceof \Composer\Autoload\ClassLoader) {
            return $map;
        }

        // Use PSR-4 prefixes to build the map
        foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
            foreach ($dirs as $dir) {
                $dir = realpath($dir);

                if ($dir === false) {
                    continue;
                }

                // Check if this PSR-4 prefix maps to a directory within our app path
                if (!str_starts_with($appPath, $dir) && !str_starts_with($dir, $appPath)) {
                    continue;
                }

                // Scan files under this directory
                if (!is_dir($dir)) {
                    continue;
                }

                $dirIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
                    $dir,
                    \RecursiveDirectoryIterator::SKIP_DOTS,
                ));

                /** @var \SplFileInfo $file */
                foreach ($dirIterator as $file) {
                    if ($file->getExtension() !== 'php') {
                        continue;
                    }

                    $filePath = $file->getRealPath();

                    if ($filePath === false) {
                        continue;
                    }

                    // Convert file path to class name
                    $relativePath = substr($filePath, strlen($dir) + 1);
                    $className = $prefix . str_replace(['/', '.php'], ['\\', ''], $relativePath);
                    $map[$filePath] = $className;
                }
            }
        }

        return $map;
    }

    /**
     * Extract class name from a PHP file by parsing namespace and class declarations.
     *
     * @param string $filePath
     * @return string|null
     */
    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        if ($content === false) {
            return null;
        }

        $namespace = null;
        $class = null;
        $matches = null;

        // Simple regex-based extraction
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/(?:final\s+|abstract\s+|readonly\s+)*class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }

    /**
     * Log a discovery failure when debug mode is enabled.
     */
    private function logDiscoveryFailure(string $className, \ReflectionException $exception): void
    {
        if ($this->config === null || !$this->config->isDebugEnabled()) {
            return;
        }

        $message = sprintf('[Foehn] Discovery failed for class "%s": %s', $className, $exception->getMessage());

        trigger_error($message, E_USER_WARNING);
    }

    /**
     * Load cache if available and not already loaded.
     */
    private function loadCache(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $this->cacheLoaded = true;

        if ($this->cache === null || !$this->cache->isEnabled()) {
            return;
        }

        $this->cachedData = $this->cache->restore();
    }

    /**
     * Check if a discovery phase has been run.
     */
    public function hasRun(string $phase): bool
    {
        return match ($phase) {
            'early' => $this->earlyRan,
            'main' => $this->mainRan,
            'late' => $this->lateRan,
            default => false,
        };
    }

    /**
     * Get all registered discoveries.
     *
     * @return array<class-string<WpDiscovery>, WpDiscovery>
     */
    public function getDiscoveries(): array
    {
        return $this->discoveries;
    }

    /**
     * Get discovery classes for each phase.
     *
     * @return array<string, array<class-string<WpDiscovery>>>
     */
    public static function getDiscoveryPhases(): array
    {
        return [
            'early' => [
                HookDiscovery::class,
                ShortcodeDiscovery::class,
                CliCommandDiscovery::class,
                TimberModelDiscovery::class,
            ],
            'main' => [
                PostTypeDiscovery::class,
                TaxonomyDiscovery::class,
                MenuDiscovery::class,
                AcfBlockDiscovery::class,
                BlockDiscovery::class,
                BlockPatternDiscovery::class,
            ],
            'late' => [
                ViewComposerDiscovery::class,
                TemplateControllerDiscovery::class,
                RestRouteDiscovery::class,
            ],
        ];
    }

    /**
     * Get all discovery classes.
     *
     * @return array<class-string<WpDiscovery>>
     */
    public static function getAllDiscoveryClasses(): array
    {
        /** @var array<class-string<WpDiscovery>> */
        return array_merge(
            self::getDiscoveryPhases()['early'],
            self::getDiscoveryPhases()['main'],
            self::getDiscoveryPhases()['late'],
        );
    }
}
