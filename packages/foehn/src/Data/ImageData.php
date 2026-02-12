<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Data;

use Studiometa\Foehn\Concerns\HasToArray;
use Studiometa\Foehn\Contracts\Arrayable;

/**
 * DTO for image/attachment fields.
 *
 * Matches the output of ACF image fields (return_format: id)
 * as used by ResponsiveImageBuilder.
 */
final readonly class ImageData implements Arrayable
{
    use HasToArray;

    public function __construct(
        public int $id,
        public string $src,
        public string $alt = '',
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    /**
     * Create from a WordPress attachment ID.
     *
     * Returns null if the ID is invalid or the attachment doesn't exist.
     *
     * @param int|null $id WordPress attachment ID
     * @param string $size Image size to retrieve
     */
    public static function fromAttachmentId(?int $id, string $size = 'large'): ?self
    {
        if ($id === null || $id === 0) {
            return null;
        }

        $src = wp_get_attachment_image_url($id, $size);

        if ($src === false) {
            return null;
        }

        $meta = wp_get_attachment_metadata($id);
        $meta = is_array($meta) ? $meta : [];

        return new self(
            id: $id,
            src: $src,
            alt: get_post_meta($id, '_wp_attachment_image_alt', true) ?: '',
            width: $meta['width'] ?? null,
            height: $meta['height'] ?? null,
        );
    }
}
