<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use Throwable;
use WP;

/**
 * Answers `/_image/<path>?w=…&h=…&fit=…&fm=…` with the transformed image.
 *
 * This runs on a **cache miss only**, and that is the whole design. The cache
 * path is spelled out from the parameters, so a webserver rule serves every hit
 * without reaching PHP. Booting WordPress costs more than the transform saves.
 *
 * Which also decides where a rate limit belongs: on the miss path, where the CPU
 * is actually spent, and not on the route as a whole. See docs/guide/images.md
 * for both rules; without them the site still works and pays a boot per image.
 */
#[AsRewriteRule(
    regex: '^' . GlideTransformer::ROUTE . '/(.+)$',
    query: 'index.php?foehn_image=$matches[1]',
    queryVars: ['foehn_image'],
)]
final readonly class GlideRoute implements RewriteHandlerInterface
{
    public function __construct(
        private GlideConfig $config,
    ) {}

    public function handle(WP $wp): void
    {
        $chemin = (string) ($wp->query_vars['foehn_image'] ?? '');

        if ($chemin === '') {
            return;
        }

        // Everything the request asked for is thrown away except the four
        // parameters a transform is made of, and those must be on the grid. This
        // is what stands in for a signature, and it has to be an allowlist of
        // *keys* rather than of values: `Server::getAllParams()` finishes with
        // `array_merge($all, $params)`, so any key that survives to Glide beats
        // whatever this side configured. An unknown key is not ignored, it wins.
        $params = $this->config->normalise($_GET);

        if ($params === null) {
            $this->fail(400, 'Transformation hors limites : ' . http_build_query($_GET));
        }

        try {
            $this->config->server()->outputImage($chemin, $params);
        } catch (Throwable $erreur) {
            // A transform that cannot be produced — a missing original, an
            // unreadable format — is a 404 for that URL, not a 500 for the site.
            $this->fail(404, $erreur->getMessage());
        }

        exit();
    }

    /**
     * @param int $status HTTP status to answer with
     */
    private function fail(int $status, string $raison): never
    {
        status_header($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');

        // The reason goes to the log, never to the response: it names paths.
        if (defined('WP_DEBUG') && constant('WP_DEBUG')) {
            error_log(sprintf('[foehn] image %d : %s', $status, $raison));
        }

        echo $status === 400 ? "Bad Request\n" : "Not Found\n";

        exit();
    }
}
