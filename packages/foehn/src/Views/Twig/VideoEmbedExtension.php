<?php

declare(strict_types=1);

namespace Studiometa\Foehn\Views\Twig;

use Studiometa\Foehn\Helpers\VideoEmbed;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for video URL transformation.
 *
 * Provides filters and functions for working with YouTube and Vimeo URLs.
 */
final class VideoEmbedExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'video_embed';
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('video_embed', [VideoEmbed::class, 'embedUrl']),
            new TwigFunction('video_id', [VideoEmbed::class, 'extractId']),
            new TwigFunction('video_platform', [VideoEmbed::class, 'detectPlatform']),
            new TwigFunction('video_is_supported', [VideoEmbed::class, 'isSupported']),
        ];
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('video_embed', [VideoEmbed::class, 'embedUrl']),
            new TwigFilter('video_id', [VideoEmbed::class, 'extractId']),
            new TwigFilter('video_platform', [VideoEmbed::class, 'detectPlatform']),
            new TwigFilter('video_is_supported', [VideoEmbed::class, 'isSupported']),
        ];
    }
}
