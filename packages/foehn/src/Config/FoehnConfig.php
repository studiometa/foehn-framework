<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

use InvalidArgumentException;
use Studiometa\Foehn\Helpers\Env;
use Tempest\Discovery\DiscoveryCacheStrategy;

/**
 * Core configuration for Føhn.
 *
 * Can be configured via a config file (preferred) or via Kernel::boot() array (legacy).
 *
 * Config file approach:
 * ```php
 * // app/foehn.config.php
 * use Studiometa\Foehn\Config\FoehnConfig;
 * use Tempest\Discovery\DiscoveryCacheStrategy;
 *
 * return new FoehnConfig(
 *     discoveryCacheStrategy: DiscoveryCacheStrategy::FULL,
 *     hooks: [CleanHeadTags::class],
 * );
 * ```
 *
 * For other configurations, use dedicated config files:
 * - `app/timber.config.php` → TimberConfig
 * - `app/rest.config.php` → RestConfig
 *
 * A package may ship its own: studiometa/foehn-acf supplies the default
 * AcfConfig that way, and `app/acf.config.php` still overrides it.
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
         * Image transformer to use, as a class implementing `ImageTransformer`.
         *
         * Null means `NullTransformer`: `image_url()` gives back the URL it was
         * given, so a template written against the interface renders correctly on
         * a project that transforms nothing.
         *
         * @var class-string|null
         */
        public ?string $imageTransformer = null,

        /**
         * Enable debug mode for discovery.
         * When enabled, reflection failures are logged via trigger_error().
         * Defaults to WP_DEBUG constant value.
         */
        public bool $debug = false,

        /**
         * Record errors to the log `wp foehn errors` reads.
         * Null records them when the environment is `local` or `development`.
         */
        public ?bool $recordErrors = null,

        /**
         * Trace front-end requests for `wp foehn trace`.
         * Null traces them when the environment is `local` or `development`.
         */
        public ?bool $traceRequests = null,

        /**
         * The WP-CLI command the project's own #[AsCliCommand] classes go under:
         * `wp app <name>` by default. The framework's commands stay under `wp foehn`.
         * One word of lowercase letters, digits, `-` and `_`, and not `foehn`.
         */
        public string $cliNamespace = 'app',
    ) {
        // WP-CLI splits a command name on spaces, so an empty name or one with a
        // space registers the commands where `wp` cannot reach them. `foehn` would
        // put the project's commands back among the framework's, where one with the
        // same name silently replaces the other.
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $cliNamespace) !== 1 || $cliNamespace === 'foehn') {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not a valid cliNamespace: use one word of lowercase letters, digits, "-" and "_", other than "foehn".',
                $cliNamespace,
            ));
        }
    }

    /**
     * Create config from array (typically from Kernel::boot config).
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $strategy = DiscoveryCacheStrategy::NONE;

        if (($config['discovery_cache'] ?? null) !== null) {
            $strategy = DiscoveryCacheStrategy::resolveFromInput($config['discovery_cache']);
        }

        /** @var list<class-string> $hooks */
        $hooks = $config['hooks'] ?? [];

        /** @var class-string|null $transformer */
        $transformer = $config['image_transformer'] ?? null;

        // Default debug to WP_DEBUG constant if not explicitly set
        $debug = $config['debug'] ?? defined('WP_DEBUG') && constant('WP_DEBUG');

        return new self(
            discoveryCacheStrategy: $strategy,
            discoveryCachePath: $config['discovery_cache_path'] ?? null,
            hooks: $hooks,
            imageTransformer: $transformer,
            debug: (bool) $debug,
            recordErrors: ($config['record_errors'] ?? null) === null ? null : (bool) $config['record_errors'],
            traceRequests: ($config['trace_requests'] ?? null) === null ? null : (bool) $config['trace_requests'],
            cliNamespace: $config['cli_namespace'] ?? 'app',
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
     * Whether errors are recorded to the log `wp foehn errors` reads.
     *
     * Off in production unless a project turns it on: an entry holds paths, messages
     * and URLs, and writing it costs a file write per error.
     */
    public function recordsErrors(): bool
    {
        return $this->recordErrors ?? in_array(Env::get(), ['local', 'development'], true);
    }

    /**
     * Whether front-end requests are traced for `wp foehn trace`.
     *
     * Off in production unless a project turns it on: each request then writes a file
     * and sends its id in a response header.
     */
    public function tracesRequests(): bool
    {
        return $this->traceRequests ?? in_array(Env::get(), ['local', 'development'], true);
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
