<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use Studiometa\Foehn\Contracts\ImageTransformer;
use Throwable;

/**
 * Transforms served by this site, through `league/glide`.
 *
 * Only the URL is built here. The transform itself happens in `GlideRoute`, once
 * per (image, transform), and the result is written to a cache a webserver can
 * serve without PHP.
 *
 * ## Why these URLs carry no signature
 *
 * A cold transform costs real CPU, so `?w=9999` must not be an instruction the
 * site will follow. Two ways to stop that: sign the URL so only transforms the
 * site asked for exist, or bound what may be asked for at all. This does the
 * second.
 *
 * Bounding it is what lets a browser build these URLs. A signature would have to
 * be computed with a secret, so a client could only ever replay sizes the server
 * chose in advance — which is the thing a responsive image is trying not to do.
 * `GlideConfig::normalise()` is the bound, and it runs on both ends: here to
 * produce a URL that is valid by construction, and in the route to refuse one
 * that is not.
 *
 * What it does not do is cap the *rate*. Walking the bounded space is still work,
 * and the control that fits is a rate limit on the miss path — cache hits never
 * reach PHP, so only transforms are throttled. See docs/guide/images.md.
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
            $params = $this->config->normalise($transform);
        } catch (Throwable) {
            return $src;
        }

        // A transform this site would refuse to serve is one it should not link
        // to. The source URL is a slower page; a URL that 400s is a broken image.
        if ($params === null) {
            return $src;
        }

        return home_url('/' . self::ROUTE . '/' . $chemin) . '?' . $this->query($params);
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
     * The query string, with the parameters always in the same order.
     *
     * The order does not change which file is cached — the cache key is built from
     * named parameters, so any order finds the same one. It is fixed so that two
     * templates asking for the same crop emit the same URL, character for
     * character, and a CDN or a browser cache sees one image rather than several.
     *
     * @param array<string, string> $params
     */
    private function query(array $params): string
    {
        $ordered = [];

        foreach (GlideConfig::PARAMS as $cle) {
            $valeur = $params[$cle] ?? null;

            if ($valeur === null) {
                continue;
            }

            $ordered[$cle] = $valeur;
        }

        return http_build_query($ordered);
    }
}
