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
 * The sizes a theme adds with `add_image_size()` or #[AsImageSize] stay too,
 * also under the name of a core size (`medium`, `medium_large`, `large`). Core
 * adds `1536x1536` and `2048x2048` with `add_image_size()` itself, so these two
 * always go. Removing the sizes also removes them from the size list of the
 * image block.
 */
final class CleanImageSizes
{
    /**
     * The core sizes WordPress reads from its options. A theme size with one of
     * these names stays.
     */
    public const array OPTION_SIZES = ['medium', 'medium_large', 'large'];

    /**
     * The core sizes that core adds with `add_image_size()`.
     */
    public const array CORE_ADDED_SIZES = ['1536x1536', '2048x2048'];

    /**
     * @param list<string> $sizes
     * @return list<string>
     */
    #[AsFilter('intermediate_image_sizes')]
    public function removeUnnecessarySizes(array $sizes): array
    {
        $themeSizes = array_keys(wp_get_additional_image_sizes());

        return array_values(array_filter($sizes, static fn(string $size): bool => match (true) {
            in_array($size, self::CORE_ADDED_SIZES, true) => false,
            in_array($size, self::OPTION_SIZES, true) => in_array($size, $themeSizes, true),
            default => true,
        }));
    }
}
