<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Hooks\Cleanup;

use Studiometa\Foehn\Attributes\AsFilter;

/**
 * Keep `thumbnail` as the only core image size WordPress writes.
 *
 * WordPress writes a file for each intermediate size on every upload: medium,
 * medium_large, large, 1536x1536 and 2048x2048. A theme that sizes its images
 * with an image transformer (Glide) reads the original and uses none of them.
 * `thumbnail` stays, because the media library and the admin show it.
 *
 * The sizes a theme adds with `add_image_size()` or #[AsImageSize] stay too.
 * Removing the sizes also removes them from the size list of the image block.
 */
final class CleanImageSizes
{
    /**
     * The core sizes that are not written.
     */
    public const array REMOVED_SIZES = ['medium', 'medium_large', 'large', '1536x1536', '2048x2048'];

    /**
     * @param list<string> $sizes
     * @return list<string>
     */
    #[AsFilter('intermediate_image_sizes')]
    public function removeUnnecessarySizes(array $sizes): array
    {
        return array_values(array_filter(
            $sizes,
            static fn(string $size): bool => !in_array($size, self::REMOVED_SIZES, true),
        ));
    }
}
