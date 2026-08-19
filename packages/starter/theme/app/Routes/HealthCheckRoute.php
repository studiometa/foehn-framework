<?php

declare(strict_types=1);

namespace App\Routes;

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use WP;

/**
 * A URL the theme answers itself, at `/_health`.
 *
 * The shape every webhook, form handler and signed URL takes: one class
 * declares the URL and answers it, before WordPress runs the main query and
 * renders anything. Written without a rewrite rule, this is a
 * `template_redirect` hook reading `$_SERVER['REQUEST_URI']` — the thing
 * rewrite rules exist to avoid.
 *
 * Replace it with your own, or delete it.
 */
#[AsRewriteRule(regex: '^_health/?$', query: 'index.php?foehn_route=health', queryVars: ['foehn_route'])]
final readonly class HealthCheckRoute implements RewriteHandlerInterface
{
    public function handle(WP $wp): void
    {
        status_header(200);
        header('Content-Type: application/json');

        echo (string) json_encode(['status' => 'ok']);

        exit();
    }
}
