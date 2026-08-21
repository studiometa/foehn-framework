<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use League\Glide\Signatures\SignatureException;
use League\Glide\Signatures\SignatureFactory;
use Studiometa\Foehn\Attributes\AsRewriteRule;
use Studiometa\Foehn\Contracts\RewriteHandlerInterface;
use Throwable;
use WP;

/**
 * Answers `/_image/<path>?w=…&s=…` with the transformed image.
 *
 * This runs on a **cache miss only**, and that is the whole design. Glide keys a
 * result under a deterministic path, so a webserver rule can serve every hit
 * straight from the cache and never reach PHP. Booting WordPress costs more than
 * the transform saves.
 *
 * See docs/guide/images.md for the nginx rule; without it the site still works,
 * it just pays a WordPress boot per image.
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

        // The signature is checked before anything is read or written. An
        // unsigned request is not a 404 to be logged and forgotten: it is someone
        // asking this site to spend CPU on their behalf.
        try {
            SignatureFactory::create($this->config->signingKey())->validateRequest(
                '/' . GlideTransformer::ROUTE . '/' . $chemin,
                $_GET,
            );
        } catch (SignatureException) {
            $this->fail(403, 'Signature invalide.');
        }

        $params = array_diff_key($_GET, ['s' => null]);

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

        echo $status === 403 ? "Forbidden\n" : "Not Found\n";

        exit();
    }
}
