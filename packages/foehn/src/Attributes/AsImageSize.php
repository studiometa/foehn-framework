<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Attributes;

use Attribute;

/**
 * Register a custom image size in WordPress.
 *
 * Usage:
 * ```php
 * #[AsImageSize(width: 1200, height: 630)]
 * final class SocialImage {}
 *
 * #[AsImageSize(width: 800, height: 600, crop: true)]
 * final class ThumbnailLarge {}
 *
 * #[AsImageSize(name: 'hero', width: 1920, height: 1080, crop: true)]
 * final class HeroImage {}
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsImageSize
{
    /**
     * @param int $width Image width in pixels
     * @param int $height Image height in pixels (0 for proportional)
     * @param bool $crop Whether to crop the image to exact dimensions
     * @param string|null $name Custom image size name (derived from class name if null)
     */
    public function __construct(
        public int $width,
        public int $height = 0,
        public bool $crop = false,
        public ?string $name = null,
    ) {}
}
