<?php

declare(strict_types=1);

namespace Studiometa\Foehn;

use RuntimeException;
use Studiometa\Foehn\Blocks\AcfBlockRenderer;
use Studiometa\Foehn\Config\AcfConfig;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Config\RenderApiConfig;
use Studiometa\Foehn\Config\RestConfig;
use Studiometa\Foehn\Config\TimberConfig;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\DiscoveryCache;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Rest\RenderApi;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\TimberViewEngine;
use Tempest\Container\Container;
use Tempest\Core\Tempest;
use Timber\Timber;

/**
 * The main kernel that bootstraps Foehn.
 */
final class Kernel
{
    private static ?self $instance = null;

    private Container $container;

    private FoehnConfig $foehnConfig;

    private bool $booted = false;

    /**
     * @param string $appPath Path to the app directory to scan for discovery
     * @param array<string, mixed> $config Legacy configuration options (prefer foehn.config.php)
     */
    private function __construct(
        private readonly string $appPath,
        private readonly array $config = [],
    ) {}

    /**
     * Boot the kernel.
     *
     * @param string $appPath Path to the app directory to scan for discovery
     * @param array<string, mixed> $config Configuration options (legacy, prefer foehn.config.php)
     *   - discovery_cache: string|bool - Cache strategy ('full', 'partial', 'none', true, false)
     *   - discovery_cache_path: string - Custom path for cache files
     *   - hooks: list<class-string> - Opt-in hook classes to activate
     *   - debug: bool - Enable debug mode for discovery
     */
    public static function boot(string $appPath, array $config = []): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        self::$instance = new self($appPath, $config);
        self::$instance->bootstrap();

        return self::$instance;
    }

    /**
     * Get the kernel instance.
     *
     * @throws RuntimeException If the kernel has not been booted
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Kernel not booted. Call Kernel::boot() first.');
        }

        return self::$instance;
    }

    /**
     * Get the container instance.
     */
    public static function container(): Container
    {
        return self::getInstance()->container;
    }

    /**
     * Get a service from the container.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function get(string $class): object
    {
        return self::container()->get($class);
    }

    /**
     * Get the app path.
     */
    public function getAppPath(): string
    {
        return $this->appPath;
    }

    /**
     * Get a configuration value.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get the Foehn configuration.
     */
    public function getFoehnConfig(): FoehnConfig
    {
        return $this->foehnConfig;
    }

    /**
     * Check if the kernel has been booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Reset the kernel instance (for testing purposes).
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Bootstrap the kernel.
     */
    private function bootstrap(): void
    {
        // Initialize Tempest container
        $this->initializeTempest();

        // Register core services
        $this->registerCoreServices();

        // Initialize Timber
        $this->initializeTimber();

        // Hook into WordPress lifecycle
        $this->registerWordPressHooks();
    }

    /**
     * Initialize Tempest framework.
     */
    private function initializeTempest(): void
    {
        // Boot Tempest with the project root (where composer.json lives)
        Tempest::boot(self::findProjectRoot($this->appPath));

        // Get the container from Tempest
        $this->container = \Tempest\Container\get(Container::class);
    }

    /**
     * Walk up from the given path to find the project root containing
     * both composer.json and a vendor/ directory.
     *
     * @throws RuntimeException If the project root cannot be found in any parent directory
     */
    private static function findProjectRoot(string $path): string
    {
        $directory = realpath($path);

        if ($directory === false) {
            throw new RuntimeException("Path does not exist: {$path}");
        }

        $previous = null;

        while ($directory !== $previous) {
            if (file_exists($directory . '/composer.json') && is_dir($directory . '/vendor')) {
                return $directory;
            }

            $previous = $directory;
            $directory = dirname($directory);
        }

        throw new RuntimeException(
            "Could not locate project root (composer.json + vendor/) in any parent directory of: {$path}",
        );
    }

    /**
     * Register core services in the container.
     */
    private function registerCoreServices(): void
    {
        // Register the kernel itself
        $this->container->singleton(self::class, fn() => $this);

        // Resolve FoehnConfig: prefer discovered config file, fall back to boot() array, then defaults
        if ($this->container->has(FoehnConfig::class)) {
            $this->foehnConfig = $this->container->get(FoehnConfig::class);
        } elseif ($this->config !== []) {
            $this->foehnConfig = FoehnConfig::fromArray($this->config);
            $this->container->singleton(FoehnConfig::class, fn() => $this->foehnConfig);
        } else {
            $this->foehnConfig = new FoehnConfig();
            $this->container->singleton(FoehnConfig::class, fn() => $this->foehnConfig);
        }

        // Register default configs only if user hasn't provided their own via *.config.php
        // Tempest's LoadConfig discovery runs before this, so user configs are already registered
        if (!$this->container->has(TimberConfig::class)) {
            $this->container->singleton(TimberConfig::class, static fn() => new TimberConfig());
        }

        if (!$this->container->has(AcfConfig::class)) {
            $this->container->singleton(AcfConfig::class, static fn() => new AcfConfig());
        }

        if (!$this->container->has(RestConfig::class)) {
            $this->container->singleton(RestConfig::class, static fn() => new RestConfig());
        }

        if (!$this->container->has(RenderApiConfig::class)) {
            $this->container->singleton(RenderApiConfig::class, static fn() => new RenderApiConfig());
        }

        // Register discovery cache
        $this->container->singleton(DiscoveryCache::class, fn() => new DiscoveryCache($this->foehnConfig));

        // Register the discovery runner with cache support and app path
        $this->container->singleton(
            DiscoveryRunner::class,
            fn() => new DiscoveryRunner($this->container, $this->container->get(DiscoveryCache::class), $this->appPath),
        );

        // Register ACF block renderer with config
        $this->container->singleton(
            AcfBlockRenderer::class,
            fn() => new AcfBlockRenderer($this->container->get(AcfConfig::class)),
        );

        // Register context provider registry
        $this->container->singleton(ContextProviderRegistry::class, static fn() => new ContextProviderRegistry());

        // Register view engine interface binding
        $this->container->singleton(
            ViewEngineInterface::class,
            fn() => new TimberViewEngine($this->container->get(ContextProviderRegistry::class)),
        );

        // Register RenderApi (used by RenderApiHook when opted-in)
        $this->container->singleton(
            RenderApi::class,
            fn() => new RenderApi(
                $this->container->get(ViewEngineInterface::class),
                $this->container->get(RenderApiConfig::class),
            ),
        );
    }

    /**
     * Initialize Timber if available.
     */
    private function initializeTimber(): void
    {
        if (!class_exists(Timber::class)) {
            add_action('admin_notices', static function (): void {
                echo
                    '<div class="error"><p><strong>Foehn:</strong> Timber plugin is required but not active.</p></div>'
                ;
            });

            if (!is_admin()) {
                return;
            }

            return;
        }

        Timber::init();

        // Set Timber templates directory from config
        /** @var TimberConfig $timberConfig */
        $timberConfig = $this->container->get(TimberConfig::class);
        Timber::$dirname = $timberConfig->templatesDir;

        add_filter('timber/context', static function (array $context): array {
            $context['site'] = new \Timber\Site();

            return $context;
        });
    }

    /**
     * Register WordPress lifecycle hooks.
     */
    private function registerWordPressHooks(): void
    {
        // Early phase: after_setup_theme
        add_action('after_setup_theme', $this->onAfterSetupTheme(...), 1);

        // Main phase: init
        add_action('init', $this->onInit(...), 1);

        // Late phase: wp_loaded
        add_action('wp_loaded', $this->onWpLoaded(...), 1);
    }

    /**
     * Handle after_setup_theme hook.
     * Run early discoveries (theme setup, Timber init).
     */
    public function onAfterSetupTheme(): void
    {
        /** @var DiscoveryRunner $runner */
        $runner = $this->container->get(DiscoveryRunner::class);
        $runner->runEarlyDiscoveries();
    }

    /**
     * Handle init hook.
     * Run main discoveries (post types, taxonomies, blocks).
     */
    public function onInit(): void
    {
        /** @var DiscoveryRunner $runner */
        $runner = $this->container->get(DiscoveryRunner::class);
        $runner->runMainDiscoveries();

        $this->booted = true;
    }

    /**
     * Handle wp_loaded hook.
     * Run late discoveries (template controllers, REST routes).
     */
    public function onWpLoaded(): void
    {
        /** @var DiscoveryRunner $runner */
        $runner = $this->container->get(DiscoveryRunner::class);
        $runner->runLateDiscoveries();
    }
}
