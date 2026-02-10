<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

/**
 * Environment detection helpers.
 *
 * Provides a consistent API for detecting the current environment
 * and debug mode, supporting multiple env variable conventions.
 *
 * @see https://github.com/studiometa/foehn/issues/55
 */
final class Env
{
    /**
     * Get the current environment name.
     *
     * Checks APP_ENV, WP_ENV, or falls back to 'production'.
     */
    public static function get(): string
    {
        return getenv('APP_ENV') ?: getenv('WP_ENV') ?: 'production';
    }

    /**
     * Check if the current environment matches.
     */
    public static function is(string $environment): bool
    {
        return self::get() === $environment;
    }

    /**
     * Check if running in production.
     */
    public static function isProduction(): bool
    {
        return self::is('production');
    }

    /**
     * Check if running in development.
     */
    public static function isDevelopment(): bool
    {
        return self::is('development');
    }

    /**
     * Check if running in staging.
     */
    public static function isStaging(): bool
    {
        return self::is('staging');
    }

    /**
     * Check if running in a local environment.
     *
     * Returns true for both 'local' and 'development' environments.
     */
    public static function isLocal(): bool
    {
        return self::is('local') || self::is('development');
    }

    /**
     * Check if WordPress debug mode is enabled.
     */
    public static function isDebug(): bool
    {
        return defined('WP_DEBUG') && \WP_DEBUG;
    }
}
