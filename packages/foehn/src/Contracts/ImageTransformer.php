<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Contracts;

/**
 * Turns an image URL plus a transform into the URL of the transformed image.
 *
 * One interface, several providers: a self-hosted transformer, a SaaS one, or
 * none at all. A theme asks for a size and does not learn which. Swapping
 * provider is a line in `foehn.config.php`, not an edit to every template — and
 * a project can run one locally and another in production.
 *
 * The transform is a plain array so it can travel through a Twig call. Drivers
 * agree on these keys, and ignore what they cannot honour:
 *
 * - `w`, `h`: target width and height, in pixels
 * - `fit`: `crop` (fill and cut), `contain` (fit inside), `stretch`
 * - `fm`: output format — `webp`, `avif`, `jpg`, `png`
 * - `q`: quality, 0-100
 *
 * A driver that cannot produce a transform returns the source URL. A missing
 * size is a slower page; a broken URL is a missing image.
 */
interface ImageTransformer
{
    /**
     * @param string $src The image URL, as WordPress reports it
     * @param array<string, string|int> $transform
     * @return string A URL serving the transformed image, or `$src` unchanged
     */
    public function url(string $src, array $transform): string;

    /**
     * Forget every transform derived from this image.
     *
     * Called when an attachment is edited or deleted. Cache keys are built from
     * the path and the transform, never from the content, so an image replaced at
     * the same path would otherwise keep serving its old crops.
     */
    public function forget(string $src): void;
}
