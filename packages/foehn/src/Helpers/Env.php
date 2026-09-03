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
 * 4. the `WP_ENV` environment variable, an accepted alias for it;
 * 5. `production`.
 *
 * Steps 2 to 4 exist for the page-cache drop-in, which runs from `wp-settings.php`
 * before `wp-includes/load.php` has defined the function. Production is the default
 * because it is the answer that makes the framework behave most conservatively — and
 * because it is what WordPress defaults to.
 *
 * **`WP_ENV` is an alias, and the generated `wp-config.php` honours it too.** That
 * second half is what makes it an alias rather than a trap. `wp_get_environment_type()`
 * reads the *constant*, and step 1 wins whenever WordPress is loaded — so a name known
 * only here would be honoured by the drop-in, which runs before the constant exists,
 * and ignored by every reader after it. One site, two environments, and the page cache
 * on the wrong side of the disagreement. The installer defines `WP_ENVIRONMENT_TYPE`
 * from either name, so this fallback only ever answers for a reader that arrives before
 * the constant does.
 *
 * `APP_ENV` is not read. It was never a WordPress name, nothing this framework
 * generates has ever written it, and one alias is as many as a single question should
 * have.
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

        return self::variable('WP_ENVIRONMENT_TYPE') ?? self::variable('WP_ENV') ?? 'production';
    }

    /**
     * An environment variable, with an empty value read as absent.
     *
     * Empty rather than merely unset, because an `.env` line with nothing after the `=`
     * is how a variable comes to exist without a value — and that has to fall through to
     * the alias below it rather than answer for it.
     */
    private static function variable(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
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
