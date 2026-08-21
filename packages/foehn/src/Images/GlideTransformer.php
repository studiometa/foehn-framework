<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use League\Glide\Signatures\SignatureFactory;
use Studiometa\Foehn\Contracts\ImageTransformer;
use Throwable;

/**
 * Transforms served by this site, through `league/glide`.
 *
 * Only the URL is built here. The transform itself happens in
 * `GlideRoute`, once per (image, transform), and the result is written to a
 * cache a webserver can serve without PHP.
 *
 * ## Why the URLs are signed
 *
 * Every URL carries an HMAC of its path and parameters. Without one, `?w=9999`
 * is an instruction to spend CPU and disk on demand: a cold transform costs a few
 * hundred milliseconds, so an ordinary crawler is enough to hurt, and a
 * deliberate one fills the cache. The signature means the only transforms that
 * exist are the ones the site asked for.
 *
 * The key is the site's `NONCE_SALT`. It is already required, already secret, and
 * already specific to the install, so signatures do not survive being copied to
 * another site — which is the behaviour you want.
 */
final readonly class GlideTransformer implements ImageTransformer
{
    /** Where the route lives, in the site's URL space. */
    public const string ROUTE = '_image';

    public function __construct(
        private GlideConfig $config,
    ) {}

    /**
     * @param array<string, string|int> $transform
     */
    public function url(string $src, array $transform): string
    {
        $chemin = $this->config->relativePath($src);

        // An image from somewhere else — another site, a remote avatar — is not
        // ours to transform. Returning it untouched is the honest answer.
        if ($chemin === null || $transform === []) {
            return $src;
        }

        try {
            $params = SignatureFactory::create($this->config->signingKey())->addSignature(
                '/' . self::ROUTE . '/' . $chemin,
                $this->normalise($transform),
            );
        } catch (Throwable) {
            return $src;
        }

        return home_url('/' . self::ROUTE . '/' . $chemin) . '?' . http_build_query($params);
    }

    public function forget(string $src): void
    {
        $chemin = $this->config->relativePath($src);

        if ($chemin === null) {
            return;
        }

        try {
            // Glide keys a transform under a directory named after its source, so
            // forgetting an image is deleting that directory — no need to know
            // which transforms were ever asked for.
            $cache = $this->config->cacheFilesystem();

            if ($cache->directoryExists($chemin)) {
                $cache->deleteDirectory($chemin);
            }
        } catch (Throwable) {
            // A cache that cannot be pruned is a stale image, not a broken page.
        }
    }

    /**
     * The parameters, as strings, in a stable order.
     *
     * The signature covers the parameters, so two URLs meaning the same thing have
     * to produce the same string — otherwise the same crop is cached twice and
     * signed differently.
     *
     * @param array<string, string|int> $transform
     * @return array<string, string>
     */
    private function normalise(array $transform): array
    {
        $params = [];

        foreach ($transform as $cle => $valeur) {
            $params[$cle] = (string) $valeur;
        }

        ksort($params);

        return $params;
    }
}
