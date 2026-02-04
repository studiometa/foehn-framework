<?php

declare(strict_types=1);

namespace Studiometa\WPTempest\Hooks\Cleanup;

use Studiometa\WPTempest\Attributes\AsFilter;

/**
 * Remove unnecessary intermediate image sizes.
 *
 * WordPress generates several intermediate sizes that are rarely used
 * in custom themes:
 * - medium_large (768px wide)
 * - 1536x1536 (added in WP 5.3)
 * - 2048x2048 (added in WP 5.3)
 *
 * Removing them saves disk space and speeds up image uploads.
 */
final class CleanImageSizes
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
