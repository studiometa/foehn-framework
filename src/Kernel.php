<?php

declare(strict_types=1);

namespace Studiometa\Foehn;

use RuntimeException;
use Studiometa\Foehn\Blocks\AcfBlockRenderer;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Discovery\DiscoveryCache;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
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

    private FoehnConfig $wpTempestConfig;

    private bool $booted = false;

    /**
     * @param string $appPath Path to the app directory to scan for discovery
     * @param array<string, mixed> $config Configuration options
     */
    private function __construct(
        private readonly string $appPath,
        private readonly array $config = [],
    ) {
        $this->wpTempestConfig = FoehnConfig::fromArray($config);
    }

    /**
     * Boot the kernel.
     *
     * @param string $appPath Path to the app directory to scan for discovery
     * @param array<string, mixed> $config Configuration options
     *   - discovery_cache: string|bool - Cache strategy ('full', 'partial', 'none', true, false)
     *   - discovery_cache_path: string - Custom path for cache files
     *   - timber_templates_dir: string[] - Timber templates directory names (default: ['templates'])
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
        return $this->wpTempestConfig;
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
        $this->container = \Tempest\get(Container::class);
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

        // Register Foehn configuration
        $this->container->singleton(FoehnConfig::class, fn() => $this->wpTempestConfig);

        // Register discovery cache
        $this->container->singleton(DiscoveryCache::class, fn() => new DiscoveryCache($this->wpTempestConfig));

        // Register the discovery runner with cache support and app path
        $this->container->singleton(
            DiscoveryRunner::class,
            fn() => new DiscoveryRunner($this->container, $this->container->get(DiscoveryCache::class), $this->appPath),
        );

        // Register ACF block renderer with config
        $this->container->singleton(AcfBlockRenderer::class, fn() => new AcfBlockRenderer($this->wpTempestConfig));
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
        Timber::$dirname = $this->wpTempestConfig->timberTemplatesDir;

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
