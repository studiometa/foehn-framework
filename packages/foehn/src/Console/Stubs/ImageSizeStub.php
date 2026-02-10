<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Studiometa\Foehn\Attributes\AsImageSize;
use Tempest\Discovery\SkipDiscovery;

/**
 * DummyImageSize - Custom image size.
 *
 * This class registers a custom image size that WordPress will generate
 * when images are uploaded to the media library.
 */
#[SkipDiscovery]
#[AsImageSize(name: 'dummy-size', width: 800, height: 600, crop: true)]
final class ImageSizeStub
{
    /**
     * The registered size name.
     */
    public const NAME = 'dummy-size';

    /**
     * Get an image URL at this size.
     *
     * @param int $attachmentId The attachment ID
     * @return string|null The image URL or null if not found
     */
    public static function url(int $attachmentId): ?string
    {
        $image = wp_get_attachment_image_src($attachmentId, self::NAME);

        return $image !== false ? $image[0] : null;
    }

    /**
     * Get the image HTML tag at this size.
     *
     * @param int $attachmentId The attachment ID
     * @param array<string, string> $attr Additional attributes for the img tag
     */
    public static function html(int $attachmentId, array $attr = []): string
    {
        return wp_get_attachment_image($attachmentId, self::NAME, false, $attr);
    }
}
