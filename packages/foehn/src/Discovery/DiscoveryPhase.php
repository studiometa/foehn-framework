<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Discovery;

/**
 * When a discovery's apply() runs.
 *
 * WordPress will not accept a post type before `init` and will not accept a REST
 * route until routes are being collected, so a discovery declares the moment it
 * needs rather than the runner deciding for it.
 */
enum DiscoveryPhase: string
{
    /** `after_setup_theme` — theme support, hooks, Twig extensions, CLI commands. */
    case Early = 'early';

    /** `init` — post types, taxonomies, blocks, meta, everything registered there. */
    case Main = 'main';

    /** `wp_loaded` — REST routes, template controllers, context providers. */
    case Late = 'late';
}
