<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Attributes\AsTwigExtension;
use Studiometa\Foehn\Contracts\ImageTransformer;
use Timber\Image;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Asks the configured transformer for a size, from a template.
 *
 * ```twig
 * <img src="{{ image_url(post.thumbnail, { w: 400, h: 267, fit: 'crop' }) }}" />
 * <img src="{{ image_url(post.thumbnail, { w: 800, fm: 'webp' }) }}" />
 * ```
 *
 * A template says what size it wants, never who produces it. With no transformer
 * configured the source URL comes back and the page still renders — which is what
 * makes this safe to write everywhere.
 */
#[AsTwigExtension]
final class ImageExtension extends AbstractExtension
{
    public function __construct(
        private readonly ImageTransformer $transformer,
    ) {}

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('image_url', $this->url(...))];
    }

    /**
     * @param Image|string|null $image A Timber image, or a URL
     * @param array<string, string|int> $transform
     */
    public function url(Image|string|null $image, array $transform = []): string
    {
        $src = $image instanceof Image ? $image->src() : (string) $image;

        if ($src === '') {
            return '';
        }

        return $this->transformer->url($src, $transform);
    }
}
