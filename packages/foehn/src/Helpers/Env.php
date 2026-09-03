<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

/**
 * The one place the framework decides which environment it is running in.
 *
 * Everything that behaves differently outside production — page-cache eligibility,
 * the non-production indexing guard, the operational dashboard, production
 * verification — reads it here. Two of those disagreeing is not a cosmetic problem: a
 * site that the cache considers production and the indexing guard considers staging
 * serves cached pages that tell search engines to drop them.
 *
 * The resolution order is WordPress's own, in the order WordPress itself would answer:
 *
 * 1. `wp_get_environment_type()`, when WordPress is loaded;
 * 2. the `WP_ENVIRONMENT_TYPE` constant, for the readers that run before it is;
 * 3. the `WP_ENVIRONMENT_TYPE` environment variable;
 * 4. `production`.
 *
 * Steps 2 and 3 exist for the page-cache drop-in, which runs from `wp-settings.php`
 * before `wp-includes/load.php` has defined the function. Production is the default
 * because it is the answer that makes the framework behave most conservatively — and
 * because it is what WordPress defaults to.
 *
 * **No `.env` file is read at runtime.** A production container injects environment
 * variables without ever writing one, so a framework that needed the file would be
 * reading nothing precisely where being right matters most.
 *
 * @see https://developer.wordpress.org/reference/functions/wp_get_environment_type/
 */
final class Env
{
    /**
     * The environment WordPress reports, or the closest thing available to the caller.
     */
    public static function get(): string
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

    /**
     * Whether the current environment is the one named.
     */
    public static function is(string $environment): bool
    {
        return self::get() === $environment;
    }

    public static function isProduction(): bool
    {
        return self::is('production');
    }

    public static function isDevelopment(): bool
    {
        return self::is('development');
    }

    public static function isStaging(): bool
    {
        return self::is('staging');
    }

    /**
     * Whether this is a developer's own machine.
     *
     * Exactly `local`, and not `development` as well. WordPress defines the two as
     * separate types — `local` is a laptop, `development` is a shared server somebody
     * develops against — and folding them together would make the one question this
     * method exists to answer unanswerable.
     */
    public static function isLocal(): bool
    {
        return self::is('local');
    }

    /**
     * Whether `WP_DEBUG` is on.
     */
    public static function isDebug(): bool
    {
        // Read through constant() rather than the bare constant: the WordPress stubs
        // declare WP_DEBUG as literal false, which the analyser then folds away.
        return defined('WP_DEBUG') && (bool) constant('WP_DEBUG');
    }
}
