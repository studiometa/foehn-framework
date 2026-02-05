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
 * @see https://github.com/studiometa/foehn/issues/54
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
