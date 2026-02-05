<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Console\Stubs;

use Tempest\Discovery\SkipDiscovery;
use Timber\Post;

/**
 * DummyModel - Custom Timber model.
 *
 * Add custom methods and properties for your post type.
 */
#[SkipDiscovery]
final class ModelStub extends Post
{
    /**
     * Example: Get formatted publication date.
     */
    public function formattedDate(): string
    {
        return $this->date('F j, Y');
    }

    /**
     * Example: Get the featured image URL with fallback.
     */
    public function featuredImageUrl(string $size = 'large'): ?string
    {
        $image = $this->thumbnail();

        return $image?->src($size);
    }

    /**
     * Example: Get excerpt with custom length.
     */
    public function shortExcerpt(int $words = 20): string
    {
        return wp_trim_words($this->excerpt(), $words);
    }
}
