<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks;

use Studiometa\WPTempest\Attributes\AsFilter;

/**
 * Remove unnecessary intermediate image sizes.
 *
 * Removes the following default WordPress image sizes:
 * - medium_large (768px)
 * - 1536x1536
 * - 2048x2048
 */
final class ImageCleanupHooks
{
    /**
     * Filter intermediate image sizes to remove unnecessary ones.
     *
     * @param list<string> $sizes
     * @return list<string>
     */
    #[AsFilter('intermediate_image_sizes')]
    public function removeUnnecessarySizes(array $sizes): array
    {
        $remove = ['medium_large', '1536x1536', '2048x2048'];

        return array_values(array_filter($sizes, static fn(string $size): bool => !in_array($size, $remove, true)));
    }
}
