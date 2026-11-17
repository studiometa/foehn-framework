<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Images;

use Studiometa\Foehn\Attributes\AsAction;
use Studiometa\Foehn\Attributes\AsFilter;
use Studiometa\Foehn\Contracts\ImageTransformer;

/**
 * Forgets an image's transforms when the image itself changes.
 *
 * A cache key is built from the path and the transform, never from the content.
 * Crop an image in the media library, or replace it at the same path, and every
 * transform derived from it keeps serving the old pixels — indefinitely, since
 * nothing else would ever invalidate them.
 */
final readonly class ImageCacheHooks
{
    public function __construct(
        private ImageTransformer $transformer,
    ) {}

    /**
     * The editor writes new metadata after a crop or a rotate.
     *
     * A filter rather than an action because that is the hook WordPress offers
     * here; the metadata passes through untouched.
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    #[AsFilter('wp_update_attachment_metadata', acceptedArgs: 2)]
    public function onUpdate(array $metadata, int $attachmentId): array
    {
        $this->forget($attachmentId);

        return $metadata;
    }

    /**
     * Before deletion, while the URL can still be resolved.
     */
    #[AsAction('delete_attachment')]
    public function onDelete(int $attachmentId): void
    {
        $this->forget($attachmentId);
    }

    /**
     * Forget the transforms of the attached file, and of the full-size original
     * when WordPress keeps one beside it.
     *
     * A large upload is scaled down to `-scaled`, which becomes the attached file;
     * the upload itself stays as `original_image`. With client-side media
     * processing (WordPress 7.1), the file is swapped only after the upload, so a
     * transform asked for in between is cached under the original's path — and
     * would never be forgotten through the attached file's URL alone.
     */
    private function forget(int $attachmentId): void
    {
        $src = wp_get_attachment_url($attachmentId);

        if (is_string($src) && $src !== '') {
            $this->transformer->forget($src);
        }

        $original = wp_get_original_image_url($attachmentId);

        if (is_string($original) && $original !== '' && $original !== $src) {
            $this->transformer->forget($original);
        }
    }
}
