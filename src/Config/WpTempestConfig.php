<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Config;

use Tempest\Core\DiscoveryCacheStrategy;

/**
 * Configuration for wp-tempest.
 */
final readonly class WpTempestConfig
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
         * Defaults to wp-content/cache/wp-tempest/discovery
         */
        public ?string $discoveryCachePath = null,
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

        return new self(discoveryCacheStrategy: $strategy, discoveryCachePath: $config['discovery_cache_path'] ?? null);
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

        // Default to wp-content/cache/wp-tempest/discovery
        if (defined('WP_CONTENT_DIR')) {
            return WP_CONTENT_DIR . '/cache/wp-tempest/discovery';
        }

        // Fallback for non-WordPress context (tests)
        return sys_get_temp_dir() . '/wp-tempest/discovery';
    }
}
