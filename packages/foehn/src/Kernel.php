<?php

declare(strict_types=1);

namespace Studiometa\Foehn;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Studiometa\Foehn\Blocks\BlockEditorAssets;
use Studiometa\Foehn\Cache\TransientCache;
use Studiometa\Foehn\Config\ConfigLoader;
use Studiometa\Foehn\Config\FoehnConfig;
use Studiometa\Foehn\Config\PageCacheConfig;
use Studiometa\Foehn\Config\RestConfig;
use Studiometa\Foehn\Config\TimberConfig;
use Studiometa\Foehn\Console\ClassFileGenerator;
use Studiometa\Foehn\Contracts\CacheInterface;
use Studiometa\Foehn\Contracts\ImageTransformer;
use Studiometa\Foehn\Contracts\JobDispatcher;
use Studiometa\Foehn\Contracts\ViewEngineInterface;
use Studiometa\Foehn\Discovery\DiscoveryLocations;
use Studiometa\Foehn\Discovery\DiscoveryRunner;
use Studiometa\Foehn\Images\NullTransformer;
use Studiometa\Foehn\Indexing\IndexingProtection;
use Studiometa\Foehn\Jobs\ActionSchedulerJobDispatcher;
use Studiometa\Foehn\Jobs\JobRegistry;
use Studiometa\Foehn\PageCache\Bypass;
use Studiometa\Foehn\PageCache\CanonicalRedirect;
use Studiometa\Foehn\PageCache\Invalidator;
use Studiometa\Foehn\PageCache\Purger;
use Studiometa\Foehn\PageCache\Recorder;
use Studiometa\Foehn\PageCache\Store;
use Studiometa\Foehn\Views\ContextProviderRegistry;
use Studiometa\Foehn\Views\Sections\SectionCollector;
use Studiometa\Foehn\Views\Sections\SectionRenderer;
use Studiometa\Foehn\Views\Sections\SectionRequest;
use Studiometa\Foehn\Views\Sections\SectionResponse;
use Studiometa\Foehn\Views\TimberViewEngine;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Tempest\Container\Container;
use Tempest\Container\GenericContainer;
use Tempest\Discovery\DiscoveryCache;
use Timber\Timber;

/**
 * The main kernel that bootstraps Foehn.
 */
final class Kernel
{
    /**
     * Shape version of the cached discovery items.
     *
     * Bump this whenever an attribute's promoted constructor changes: a cache
     * written against the old signature is then ignored instead of restored.
     */
    private const DISCOVERY_CACHE_VERSION = 'foehn.discovery.v1';

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
        // Initialize the DI container
        $this->initializeContainer();

        // Register core services
        $this->registerCoreServices();

        // Initialize Timber
        $this->initializeTimber();

        // Hook into WordPress lifecycle
        $this->registerWordPressHooks();
    }

    /**
     * Initialize the DI container.
     *
     * Creates a standalone Tempest GenericContainer without booting the full
     * Tempest framework. Foehn manages its own discovery lifecycle via
     * DiscoveryRunner, so we only need the container for autowiring.
     */
    private function initializeContainer(): void
    {
        $this->container = new GenericContainer();

        GenericContainer::setInstance($this->container);
    }

    /**
     * Register core services in the container.
     */
    private function registerCoreServices(): void
    {
        // Register the kernel itself
        $this->container->singleton(self::class, fn() => $this);

        // Where classes and config files are looked for. Registered before the
        // configs because reading a config file needs the locations, and choosing
        // how discovery caches needs the config.
        $this->container->singleton(DiscoveryLocations::class, fn() => new DiscoveryLocations($this->appPath));

        // Resolve and register configurations
        $this->registerConfigs();

        // Register infrastructure services
        $this->registerInfrastructureServices();
    }

    /**
     * Resolve FoehnConfig and register default configs.
     */
    private function registerConfigs(): void
    {
        // Defaults first, so that a project only writes the config files it cares
        // about. Kernel::boot()'s array is one of those defaults: a foehn.config.php
        // replaces it wholesale, which is why the array is the legacy way in.
        $this->container->config(new TimberConfig());
        $this->container->config(new RestConfig());
        $this->container->config(new PageCacheConfig());
        $this->container->config($this->config !== [] ? FoehnConfig::fromArray($this->config) : new FoehnConfig());

        new ConfigLoader($this->container)->load($this->container->get(DiscoveryLocations::class)->all());

        /** @var FoehnConfig $foehnConfig */
        $foehnConfig = $this->container->get(FoehnConfig::class);
        $this->foehnConfig = $foehnConfig;
    }

    /**
     * Register infrastructure services (cache, discovery, views, etc.).
     */
    private function registerInfrastructureServices(): void
    {
        $this->container->singleton(CacheInterface::class, static fn() => new TransientCache());

        // The discovery cache stores attribute instances, which var_export()s back
        // through a constructor-less hydration. An attribute whose promoted
        // constructor changed would come back with properties the new code does not
        // expect, so the pool namespace carries a shape version: bump it and every
        // cache written by an older Foehn is ignored rather than half-restored.
        $this->container->singleton(
            CacheItemPoolInterface::class,
            fn() => new PhpFilesAdapter(
                namespace: self::DISCOVERY_CACHE_VERSION,
                directory: $this->foehnConfig->getDiscoveryCachePath(),
            ),
        );

        $this->container->singleton(
            DiscoveryCache::class,
            fn() => new DiscoveryCache(
                $this->foehnConfig->discoveryCacheStrategy,
                $this->container->get(CacheItemPoolInterface::class),
            ),
        );

        $this->container->singleton(ClassFileGenerator::class, fn() => new ClassFileGenerator($this->appPath));

        // The image transformer a project asked for, or none. Bound to the
        // interface so a template, a Twig function and the invalidation hooks all
        // receive the same one without naming a driver.
        $this->container->singleton(ImageTransformer::class, function (): ImageTransformer {
            $pilote = $this->foehnConfig->imageTransformer;

            if ($pilote === null || !is_a($pilote, ImageTransformer::class, true)) {
                return new NullTransformer();
            }

            return $this->container->get($pilote);
        });

        $this->container->singleton(
            DiscoveryRunner::class,
            fn() => new DiscoveryRunner(
                $this->container,
                $this->container->get(DiscoveryCache::class),
                $this->container->get(CacheItemPoolInterface::class),
                $this->container->get(DiscoveryLocations::class),
                $this->foehnConfig,
            ),
        );

        $this->container->singleton(
            BlockEditorAssets::class,
            fn() => new BlockEditorAssets($this->container->get(DiscoveryRunner::class), $this->foehnConfig),
        );

        $this->container->singleton(JobRegistry::class, static fn() => new JobRegistry());

        $this->container->singleton(
            JobDispatcher::class,
            fn() => new ActionSchedulerJobDispatcher($this->container->get(JobRegistry::class)),
        );

        $this->container->singleton(ContextProviderRegistry::class, static fn() => new ContextProviderRegistry());

        $this->container->singleton(
            ViewEngineInterface::class,
            fn() => new TimberViewEngine($this->container->get(ContextProviderRegistry::class)),
        );

        // SectionRequest and SectionCollector are request-scoped by the PHP process.
        // Explicit singleton bindings make the template controller and Twig extension
        // share the same selection and declarations.
        $this->container->singleton(SectionRequest::class, static fn() => new SectionRequest());
        $this->container->singleton(SectionCollector::class, static fn() => new SectionCollector());
        $this->container->singleton(
            SectionRenderer::class,
            fn() => new SectionRenderer($this->container->get(ViewEngineInterface::class)),
        );

        // Registered whether or not the cache is on, because `wp foehn cache:clear`
        // and `cache:status` have to work on a site that has just turned it off.
        $this->container->singleton(Store::class, fn() => new Store($this->container->get(PageCacheConfig::class)));

        // The single runtime entry point for deletion — WP-CLI, the WordPress
        // invalidation hooks and the admin controls all go through it. A singleton for
        // the same reason as the store above: files an earlier release left behind stay
        // removable on a site that has since turned the cache off.
        $this->container->singleton(
            Invalidator::class,
            fn() => new Invalidator($this->container->get(PageCacheConfig::class), $this->container->get(Store::class)),
        );

        // A singleton on every site, production included, so production verification can
        // ask it whether it is active and be told no. The instance is inert there: it
        // registers nothing unless the environment is something other than production.
        $this->container->singleton(IndexingProtection::class, static fn() => new IndexingProtection());

        $this->container->singleton(Bypass::class, fn() => new Bypass($this->container->get(PageCacheConfig::class)));

        // Below `Bypass`, because a section response asks it whether it is one the page
        // cache would store — which is what decides its `Cache-Control`.
        $this->container->singleton(
            SectionResponse::class,
            fn() => new SectionResponse(
                $this->container->get(SectionRequest::class),
                $this->container->get(Bypass::class),
            ),
        );

        $this->container->singleton(
            Recorder::class,
            fn() => new Recorder(
                $this->container->get(PageCacheConfig::class),
                $this->container->get(Store::class),
                $this->container->get(Bypass::class),
            ),
        );

        $this->container->singleton(
            Purger::class,
            fn() => new Purger(
                $this->container->get(PageCacheConfig::class),
                $this->container->get(Invalidator::class),
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

        // Editor phase: ship the block editor registrar and the block definitions.
        // Always on — block authoring must not be opt-in like the classes in src/Hooks/.
        add_action('enqueue_block_editor_assets', $this->onEnqueueBlockEditorAssets(...));

        // Not part of registerPageCache(), and not gated on it: the framework emits URLs
        // with literal commas in every environment, and core redirects them away in every
        // environment. See CanonicalRedirect.
        new CanonicalRedirect()->register();

        // Not in `FoehnConfig::hooks` either, and for a stronger reason than the redirect
        // above: an indexing guard nobody remembered to opt into is a staging site in the
        // search results. It adds nothing at all in production. See IndexingProtection.
        $this->container->get(IndexingProtection::class)->register();

        $this->registerPageCache();
    }

    /**
     * Wire the page cache, when a config file has asked for it.
     *
     * One switch, and nothing to add to `FoehnConfig::hooks`: the framework's own
     * `#[AsAction]` classes are opt-in by design, so page-cache hooks are wired here
     * instead — the way `enqueue_block_editor_assets` already is. The purger is wired
     * with the recorder rather than separately, because a cache that fills without
     * invalidating is the one failure mode worse than no cache at all.
     */
    private function registerPageCache(): void
    {
        /** @var PageCacheConfig $config */
        $config = $this->container->get(PageCacheConfig::class);

        if (!$config->enabled || !$config->allowsEnvironment()) {
            return;
        }

        /** @var Recorder $recorder */
        $recorder = $this->container->get(Recorder::class);
        $recorder->register();

        /** @var Purger $purger */
        $purger = $this->container->get(Purger::class);
        $purger->register();
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

    /**
     * Handle enqueue_block_editor_assets hook.
     * Ship the generic registrar and the discovered block definitions to the editor.
     *
     * BlockEditorAssets is resolved lazily: discoveries have not run at bootstrap time.
     */
    public function onEnqueueBlockEditorAssets(): void
    {
        /** @var BlockEditorAssets $assets */
        $assets = $this->container->get(BlockEditorAssets::class);
        $assets->enqueue();
    }
}
