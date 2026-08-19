<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

use WP;

/**
 * Answers the request a rewrite rule matched.
 *
 * Implemented alongside #[AsRewriteRule] by a class that handles its own URL
 * rather than rewriting onto an existing template.
 */
interface RewriteHandlerInterface
{
    /**
     * Handle a request the rule matched.
     *
     * Called on `parse_request`, before WordPress runs the main query. A handler
     * that answers the request itself ends with `exit`; one that only prepares
     * something returns, and WordPress carries on.
     *
     * @param WP $wp The WordPress environment, whose `query_vars` hold what the
     *   rule's query string set
     */
    public function handle(WP $wp): void;
}
