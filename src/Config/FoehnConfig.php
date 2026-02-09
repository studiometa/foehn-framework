<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

use Tempest\Core\DiscoveryCacheStrategy;

/**
 * Core configuration for Føhn.
 *
 * This config is passed to Kernel::boot() and contains options needed
 * during the bootstrap phase (before Tempest config discovery runs).
 *
 * For other configurations, use dedicated config files:
 * - `app/timber.config.php` → TimberConfig
 * - `app/acf.config.php` → AcfConfig
 * - `app/rest.config.php` → RestConfig
 * - `app/render-api.config.php` → RenderApiConfig
 */
final readonly class FoehnConfig
{
    public function __construct(
        /**
         * Discovery cache strategy.
         * - 'full': Cache all discoveries (vendor + app)
         * - 'partial': Cache only vendor discoveries
         * - 'none': Disable caching (development)
         */
        public DiscoveryCacheStrategy $discoveryCacheStrategy = DiscoveryCacheStrategy::NONE,

        /**
         * Path to store discovery cache files.
         * Defaults to wp-content/cache/foehn/discovery
         */
        public ?string $discoveryCachePath = null,

        /**
         * Opt-in hook classes to activate.
         * @var list<class-string>
         */
        public array $hooks = [],

        /**
         * Enable debug mode for discovery.
         * When enabled, reflection failures are logged via trigger_error().
         * Defaults to WP_DEBUG constant value.
         */
        public bool $debug = false,
    ) {}

    /**
     * Create config from array (typically from Kernel::boot config).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $strategy = DiscoveryCacheStrategy::NONE;

        if (isset($config['discovery_cache'])) {
            $strategy = DiscoveryCacheStrategy::make($config['discovery_cache']);
        }

        /** @var list<class-string> $hooks */
        $hooks = $config['hooks'] ?? [];

        // Default debug to WP_DEBUG constant if not explicitly set
        $debug = $config['debug'] ?? defined('WP_DEBUG') && constant('WP_DEBUG');

        return new self(
            discoveryCacheStrategy: $strategy,
            discoveryCachePath: $config['discovery_cache_path'] ?? null,
            hooks: $hooks,
            debug: (bool) $debug,
        );
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isDebugEnabled(): bool
    {
        return $this->debug;
    }

    /**
     * Check if discovery caching is enabled.
     */
    public function isDiscoveryCacheEnabled(): bool
    {
        return $this->discoveryCacheStrategy->isEnabled();
    }

    /**
     * Get the discovery cache path.
     */
    public function getDiscoveryCachePath(): string
    {
        if ($this->discoveryCachePath !== null) {
            return $this->discoveryCachePath;
        }

        // Default to wp-content/cache/foehn/discovery
        if (defined('WP_CONTENT_DIR')) {
            return constant('WP_CONTENT_DIR') . '/cache/foehn/discovery';
        }

        // Fallback for non-WordPress context (tests)
        return sys_get_temp_dir() . '/foehn/discovery';
    }
}
