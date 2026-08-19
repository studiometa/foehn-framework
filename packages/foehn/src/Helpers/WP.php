<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Helpers;

use WP_Post;
use WP_Query;
use WP_User;
use wpdb;

/**
 * Typed accessors for WordPress global variables.
 *
 * Provides a clean API to access WordPress globals while centralizing
 * the "unsafe" $GLOBALS access in a single, auditable location.
 *
 * @see https://github.com/studiometa/foehn-framework/issues/54
 */
final class WP
{
    /**
     * Get the WordPress database instance.
     */
    public static function db(): wpdb
    {
        // @mago-expect lint:no-global
        return $GLOBALS['wpdb'];
    }

    /**
     * Get the main WordPress query.
     */
    public static function query(): WP_Query
    {
        // @mago-expect lint:no-global
        return $GLOBALS['wp_query'];
    }

    /**
     * Get the current post.
     */
    public static function post(): ?WP_Post
    {
        // @mago-expect lint:no-global
        return $GLOBALS['post'] ?? null;
    }

    /**
     * Run a callback with `$post` set to a given post, then put back what was there.
     *
     * Some WordPress functions read the global rather than taking a post —
     * `get_adjacent_post()` is the one this exists for — so a caller that needs an
     * answer about a post other than the current one has to lend it the global. Doing
     * that here keeps the borrowing, and the restoring, in the auditable place.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function withPost(?WP_Post $post, callable $callback): mixed
    {
        // @mago-expect lint:no-global
        $previous = $GLOBALS['post'] ?? null;
        // @mago-expect lint:no-global
        $GLOBALS['post'] = $post;

        try {
            return $callback();
        } finally {
            // @mago-expect lint:no-global
            $GLOBALS['post'] = $previous;
        }
    }

    /**
     * Get the current user.
     *
     * Returns null if no user is logged in (user ID is 0).
     */
    public static function user(): ?WP_User
    {
        $user = wp_get_current_user();

        return $user->ID !== 0 ? $user : null;
    }
}
