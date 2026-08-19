<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Config;

/**
 * The one description of what the page cache stores, and of what it refuses to store.
 *
 * Four readers serve this cache — the `advanced-cache.php` drop-in, a generated nginx
 * snippet, a generated `.htaccess` block, and the writer's own eligibility check — and
 * three of them cannot ask WordPress anything. They all derive from this object
 * instead: the drop-in requires the config file, and `wp foehn cache:config` renders
 * the server snippets from it. Three hand-written copies of "is this request
 * cacheable" that drift apart is the defect this design exists to avoid.
 *
 * Off by default. A cache nobody asked for is a bug, so a project enables it in the
 * environment it wants it in:
 *
 * ```php
 * // app/page-cache.production.config.php
 * use Studiometa\Foehn\Config\PageCacheConfig;
 *
 * return new PageCacheConfig(
 *     enabled: true,
 *     ttl: 8 * HOUR_IN_SECONDS,
 * );
 * ```
 */
final readonly class PageCacheConfig
{
    public function __construct(
        /** Master switch. Off by default: a cache nobody asked for is a bug. */
        public bool $enabled = false,

        /** Cache root. Defaults to WP_CONTENT_DIR . '/cache/foehn/pages'. */
        public ?string $path = null,

        /** Seconds a file stays servable. 0 = until something purges it. */
        public int $ttl = 0,

        /** max-age sent to the browser for cached HTML. 0 + must-revalidate keeps purges instant. */
        public int $browserMaxAge = 0,

        /**
         * Environments where caching is allowed at all.
         *
         * @var list<string>
         */
        public array $environments = ['production'],

        /**
         * A request carrying one of these cookie prefixes is never served or written.
         *
         * @var list<string>
         */
        public array $bypassCookies = ['wordpress_logged_in_', 'comment_author_', 'wp-postpass_'],

        /**
         * Stripped before keying, so tracking parameters still hit.
         *
         * @var list<string>
         */
        public array $ignoredQueryArgs = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'utm_id',
            'gclid',
            'fbclid',
            'msclkid',
            'mc_cid',
            'mc_eid',
            '_ga',
            'ref',
        ],

        /**
         * URL path prefixes never cached.
         *
         * @var list<string>
         */
        public array $excludedPaths = [],

        /**
         * Response bodies containing one of these substrings are not cached.
         *
         * @var list<string>
         */
        public array $excludeWhenBodyContains = [],

        /**
         * Cache 404s (own TTL bucket, always short).
         *
         * Off by default is also what stops an attacker filling the disk with a
         * directory per made-up URL. Turning it on wants a bound on the entry count.
         */
        public bool $cacheNotFound = false,

        /** Emit X-Foehn-Cache headers. Defaults to WP_DEBUG. */
        public ?bool $debugHeaders = null,
    ) {}

    /**
     * The cache root, resolved.
     */
    public function getPath(): string
    {
        if ($this->path !== null) {
            return rtrim($this->path, '/');
        }

        if (defined('WP_CONTENT_DIR')) {
            return rtrim((string) constant('WP_CONTENT_DIR'), '/') . '/cache/foehn/pages';
        }

        // Non-WordPress context (tests).
        return sys_get_temp_dir() . '/foehn/pages';
    }

    /**
     * Whether the X-Foehn-Cache headers are emitted.
     *
     * Without them this feature is undebuggable in production and the smoke test has
     * nothing to assert, so debug builds get them for free.
     */
    public function wantsDebugHeaders(): bool
    {
        if ($this->debugHeaders !== null) {
            return $this->debugHeaders;
        }

        return defined('WP_DEBUG') && (bool) constant('WP_DEBUG');
    }

    /**
     * Whether caching is allowed in the environment the site reports.
     */
    public function allowsEnvironment(?string $environment = null): bool
    {
        return in_array($environment ?? self::environment(), $this->environments, true);
    }

    /**
     * The environment, resolved without needing WordPress to be loaded.
     *
     * The drop-in runs from `wp-settings.php`, after `wp-config.php` has defined
     * `WP_ENVIRONMENT_TYPE` and after `wp-includes/load.php` has defined
     * `wp_get_environment_type()`, so both are usually available. The fallbacks are
     * for the cases where neither is — and default to production, as WordPress does.
     */
    public static function environment(): string
    {
        if (function_exists('wp_get_environment_type')) {
            return wp_get_environment_type();
        }

        if (defined('WP_ENVIRONMENT_TYPE')) {
            $type = (string) constant('WP_ENVIRONMENT_TYPE');

            if ($type !== '') {
                return $type;
            }
        }

        $type = getenv('WP_ENVIRONMENT_TYPE');

        return is_string($type) && $type !== '' ? $type : 'production';
    }
}
